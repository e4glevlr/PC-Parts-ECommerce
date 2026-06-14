import uuid
from datetime import datetime
from decimal import Decimal, ROUND_HALF_UP
from typing import Optional
from sqlalchemy import or_
from sqlalchemy.orm import Session
from app.core.exceptions import ResourceNotFoundException, BadRequestException
from app.models import Order, OrderItem, Cart, CartItem, Product, User, Promotion

VALID_STATUSES = {"PENDING", "CONFIRMED", "PROCESSING", "SHIPPED", "DELIVERED", "CANCELLED"}

# Quy tắc tính giá đơn hàng
VAT_RATE = Decimal("0.1")                 # VAT 10%
SHIPPING_THRESHOLD = Decimal("10000000")  # miễn phí ship cho đơn từ 10 triệu
SHIPPING_FEE = Decimal("30000")           # phí ship mặc định 30k


def get_order_by_id(db: Session, order_id: int) -> Order:
    order = db.query(Order).filter(Order.id == order_id).first()
    if not order:
        raise ResourceNotFoundException("Đơn hàng", "id", order_id)
    return order


def get_order_by_code(db: Session, code: str) -> Order:
    order = db.query(Order).filter(Order.order_code == code).first()
    if not order:
        raise ResourceNotFoundException("Đơn hàng", "order_code", code)
    return order


def get_all_orders(db: Session, page: int, size: int, search: Optional[str] = None):
    query = db.query(Order)
    if search:
        kw = f"%{search}%"
        query = query.filter(or_(Order.order_code.ilike(kw), Order.customer_name.ilike(kw)))
    total = query.order_by(Order.created_at.desc()).count()
    items = query.order_by(Order.created_at.desc()).offset(page * size).limit(size).all()
    return items, total


def get_orders_by_user(db: Session, user_id: int, page: int, size: int):
    query = db.query(Order).filter(Order.user_id == user_id).order_by(Order.created_at.desc())
    total = query.count()
    items = query.offset(page * size).limit(size).all()
    return items, total


def get_orders_by_status(db: Session, status: str, page: int, size: int):
    query = db.query(Order).filter(Order.status == status.upper()).order_by(Order.created_at.desc())
    total = query.count()
    items = query.offset(page * size).limit(size).all()
    return items, total


# ═══════════════════════════════════════════════════════════════════════
# ★ CHECKOUT: giỏ hàng → đơn hàng, tất cả trong 1 transaction atomic
#   Giá = tiền hàng + VAT 10% + phí ship (miễn phí cho đơn từ 10 triệu)
#   Hết hàng giữa chừng → exception → rollback toàn bộ, kho không bị âm
# ═══════════════════════════════════════════════════════════════════════

def create_order_from_cart(db: Session, user_id: int, shipping_address: str,
                           notes: Optional[str] = None, shipping_phone: Optional[str] = None,
                           customer_name: Optional[str] = None, customer_email: Optional[str] = None,
                           promotion_id: Optional[int] = None) -> Order:
    # Bước 1 — Lấy giỏ hàng, từ chối nếu trống
    user = db.query(User).filter(User.id == user_id).first()
    if not user:
        raise ResourceNotFoundException("Người dùng", "id", user_id)
    cart = db.query(Cart).filter(Cart.user_id == user_id).first()
    if not cart:
        raise BadRequestException("Không tìm thấy giỏ hàng")
    cart_items = db.query(CartItem).filter(CartItem.cart_id == cart.id).all()
    if not cart_items:
        raise BadRequestException("Giỏ hàng đang trống")

    # Bước 2 — Tính giá: tiền hàng + VAT 10% + phí ship
    subtotal = sum(Decimal(str(ci.product.price)) * ci.quantity for ci in cart_items)
    tax = (subtotal * VAT_RATE).quantize(Decimal("1"), rounding=ROUND_HALF_UP)
    shipping = Decimal("0") if subtotal >= SHIPPING_THRESHOLD else SHIPPING_FEE
    gross = subtotal + tax + shipping
    code = f"ORD-{datetime.utcnow().strftime('%Y%m%d%H%M%S')}-{uuid.uuid4().hex[:6].upper()}"

    order = Order(
        order_code=code, user_id=user_id,
        customer_name=customer_name or user.full_name,
        customer_email=customer_email or user.email,
        total_amount=float(subtotal), discount_amount=0, final_amount=float(gross),
        status="PENDING", payment_method="COD",
        shipping_address=shipping_address,
        shipping_phone=shipping_phone or user.phone,  # DB yêu cầu NOT NULL — mặc định lấy SĐT tài khoản
        notes=notes,
    )

    # Bước 3 — Áp khuyến mãi nếu còn hiệu lực và đơn đạt giá trị tối thiểu
    if promotion_id:
        promo = db.query(Promotion).filter(Promotion.id == promotion_id).first()
        if promo and promo.is_active and promo.start_date <= datetime.utcnow() <= promo.end_date:
            if subtotal >= Decimal(str(promo.minimum_order_amount)):
                disc = (subtotal * Decimal(str(promo.discount_value)) / 100
                        if promo.discount_type == "PERCENTAGE"
                        else Decimal(str(promo.discount_value)))
                order.discount_amount = float(disc)
                order.final_amount = float(max(gross - disc, Decimal("0")))
                order.promotion_id = promotion_id

    db.add(order)
    db.flush()  # lấy order.id mà chưa commit — vẫn trong cùng transaction

    # Bước 4 — Trừ kho + snapshot tên/giá sản phẩm tại thời điểm mua
    for ci in cart_items:
        p = ci.product
        if not p.is_active:
            raise BadRequestException(f"Sản phẩm không khả dụng: {p.name}")
        if p.quantity < ci.quantity:
            raise BadRequestException(f"Không đủ hàng: {p.name}")
        p.quantity -= ci.quantity
        db.add(OrderItem(order_id=order.id, product_id=p.id, product_name=p.name,
                         quantity=ci.quantity, price=p.price))

    # Bước 5 — Dọn giỏ hàng và chốt transaction
    for ci in cart_items:
        db.delete(ci)
    db.commit()
    db.refresh(order)
    return order


def update_order_status(db: Session, order_id: int, status: str) -> Order:
    if status.upper() not in VALID_STATUSES:
        raise BadRequestException(f"Trạng thái không hợp lệ: {status}")
    order = get_order_by_id(db, order_id)
    order.status = status.upper()
    db.commit()
    db.refresh(order)
    return order


def cancel_order(db: Session, order_id: int) -> Order:
    order = get_order_by_id(db, order_id)
    if order.status == "DELIVERED":
        raise BadRequestException("Không thể hủy đơn đã giao")
    if order.status == "CANCELLED":
        raise BadRequestException("Đơn hàng đã bị hủy trước đó")
    for oi in db.query(OrderItem).filter(OrderItem.order_id == order_id).all():
        p = db.query(Product).filter(Product.id == oi.product_id).first()
        if p:
            p.quantity += oi.quantity
    order.status = "CANCELLED"
    db.commit()
    db.refresh(order)
    return order


def is_order_owner(db: Session, order_id: int, user_id: int) -> bool:
    return db.query(Order).filter(Order.id == order_id, Order.user_id == user_id).first() is not None
