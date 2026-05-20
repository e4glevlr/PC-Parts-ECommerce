from datetime import datetime
from typing import Optional
from sqlalchemy.orm import Session
from app.core.exceptions import ResourceNotFoundException, BadRequestException
from app.models import Cart, CartItem, Product, User


def get_or_create_cart(db: Session, user_id: int) -> Cart:
    user = db.query(User).filter(User.id == user_id).first()
    if not user:
        raise ResourceNotFoundException("Người dùng", "id", user_id)
    cart = db.query(Cart).filter(Cart.user_id == user_id).first()
    if not cart:
        cart = Cart(user_id=user_id)
        db.add(cart)
        db.commit()
        db.refresh(cart)
    return cart


def get_cart_by_user_id(db: Session, user_id: int) -> Cart:
    return get_or_create_cart(db, user_id)


def add_item_to_cart(db: Session, user_id: int, product_id: int, quantity: int) -> Cart:
    cart = get_or_create_cart(db, user_id)
    product = db.query(Product).filter(Product.id == product_id).first()
    if not product:
        raise ResourceNotFoundException("Sản phẩm", "id", product_id)
    if not product.is_active:
        raise BadRequestException("Sản phẩm hiện không hoạt động")
    if product.quantity < quantity:
        raise BadRequestException(f"Không đủ hàng: {product.name}")

    existing = db.query(CartItem).filter(CartItem.cart_id == cart.id, CartItem.product_id == product_id).first()
    if existing:
        new_qty = existing.quantity + quantity
        if product.quantity < new_qty:
            raise BadRequestException(f"Không đủ hàng: {product.name}")
        existing.quantity = new_qty
        existing.updated_at = datetime.utcnow()
    else:
        db.add(CartItem(cart_id=cart.id, product_id=product_id, quantity=quantity))

    cart.updated_at = datetime.utcnow()
    db.commit()
    db.refresh(cart)
    return cart


def update_cart_item(db: Session, user_id: int, item_id: int, quantity: int) -> Cart:
    cart = get_or_create_cart(db, user_id)
    item = db.query(CartItem).filter(CartItem.id == item_id).first()
    if not item:
        raise ResourceNotFoundException("Mục giỏ hàng", "id", item_id)
    if item.cart_id != cart.id:
        raise BadRequestException("Mục giỏ hàng không thuộc người dùng này")
    if quantity <= 0:
        raise BadRequestException("Số lượng phải lớn hơn 0")
    if item.product.quantity < quantity:
        raise BadRequestException(f"Không đủ hàng: {item.product.name}")

    item.quantity = quantity
    item.updated_at = datetime.utcnow()
    cart.updated_at = datetime.utcnow()
    db.commit()
    db.refresh(cart)
    return cart


def remove_item_from_cart(db: Session, user_id: int, item_id: int) -> Cart:
    cart = get_or_create_cart(db, user_id)
    item = db.query(CartItem).filter(CartItem.id == item_id).first()
    if not item:
        raise ResourceNotFoundException("Mục giỏ hàng", "id", item_id)
    if item.cart_id != cart.id:
        raise BadRequestException("Mục giỏ hàng không thuộc người dùng này")
    db.delete(item)
    cart.updated_at = datetime.utcnow()
    db.commit()
    db.refresh(cart)
    return cart


def clear_cart(db: Session, user_id: int):
    cart = db.query(Cart).filter(Cart.user_id == user_id).first()
    if not cart:
        return
    db.query(CartItem).filter(CartItem.cart_id == cart.id).delete()
    cart.updated_at = datetime.utcnow()
    db.commit()


def merge_guest_cart(db: Session, user_id: int, guest_items: list[dict]) -> Cart:
    cart = get_or_create_cart(db, user_id)
    for gi in guest_items:
        product = db.query(Product).filter(Product.id == gi["product_id"]).first()
        if not product or not product.is_active:
            continue
        existing = db.query(CartItem).filter(CartItem.cart_id == cart.id, CartItem.product_id == gi["product_id"]).first()
        if existing:
            new_qty = existing.quantity + gi["quantity"]
            if product.quantity >= new_qty:
                existing.quantity = new_qty
                existing.updated_at = datetime.utcnow()
        else:
            if product.quantity >= gi["quantity"]:
                db.add(CartItem(cart_id=cart.id, product_id=gi["product_id"], quantity=gi["quantity"]))
    cart.updated_at = datetime.utcnow()
    db.commit()
    db.refresh(cart)
    return cart
