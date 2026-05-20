from fastapi import APIRouter, Depends, Query
from sqlalchemy.orm import Session
from app.api.dependencies import get_current_user, require_staff, require_customer
from app.crud import order as order_crud
from app.db.session import get_db
from app.models import User, Order
from app.schemas.order import OrderRequest, OrderResponse, OrderItemResponse

router = APIRouter()


def _to_order_response(o: Order) -> dict:
    return OrderResponse(
        id=o.id, order_code=o.order_code, user_id=o.user_id,
        customer_name=o.customer_name, customer_email=o.customer_email,
        total_amount=float(o.total_amount), discount_amount=float(o.discount_amount),
        final_amount=float(o.final_amount), promotion_id=o.promotion_id,
        status=o.status, payment_method=o.payment_method,
        shipping_address=o.shipping_address, shipping_phone=o.shipping_phone, notes=o.notes,
        order_items=[OrderItemResponse(
            id=oi.id, product_id=oi.product_id, product_name=oi.product_name,
            quantity=oi.quantity, price=float(oi.price),
            primary_image_url=oi.product.primary_image_url if oi.product else None,
        ) for oi in (o.order_items or [])],
        created_at=o.created_at, updated_at=o.updated_at,
    ).model_dump()


def _paged(items, total, page, size):
    tp = max((total + size - 1) // size, 1)
    return {"content": [_to_order_response(o) for o in items], "page": page, "size": size,
            "total_elements": total, "total_pages": tp, "first": page == 0, "last": page >= tp - 1}


@router.get("")
def get_all_orders(page: int = 0, size: int = 20, search: str = Query(None),
                   db: Session = Depends(get_db), _: User = Depends(require_staff)):
    items, total = order_crud.get_all_orders(db, page, size, search)
    return {"status_code": 200, "message": "Thành công", "data": _paged(items, total, page, size)}


@router.get("/my-orders")
def get_my_orders(page: int = 0, size: int = 20, db: Session = Depends(get_db),
                  user: User = Depends(require_customer)):
    items, total = order_crud.get_orders_by_user(db, user.id, page, size)
    return {"status_code": 200, "message": "Thành công", "data": _paged(items, total, page, size)}


@router.get("/code/{order_code}")
def get_by_code(order_code: str, db: Session = Depends(get_db)):
    o = order_crud.get_order_by_code(db, order_code)
    return {"status_code": 200, "message": "Thành công", "data": _to_order_response(o)}


@router.get("/status/{status}")
def get_by_status(status: str, page: int = 0, size: int = 20, db: Session = Depends(get_db),
                  _: User = Depends(require_staff)):
    items, total = order_crud.get_orders_by_status(db, status, page, size)
    return {"status_code": 200, "message": "Thành công", "data": _paged(items, total, page, size)}


@router.get("/{order_id}")
def get_order(order_id: int, db: Session = Depends(get_db), user: User = Depends(get_current_user)):
    o = order_crud.get_order_by_id(db, order_id)
    if user.role.name not in ("ADMIN", "STAFF") and o.user_id != user.id:
        from app.core.exceptions import ForbiddenException
        raise ForbiddenException()
    return {"status_code": 200, "message": "Thành công", "data": _to_order_response(o)}


@router.post("/from-cart", status_code=201)
def create_from_cart(req: OrderRequest, db: Session = Depends(get_db),
                     user: User = Depends(require_customer)):
    o = order_crud.create_order_from_cart(
        db, user.id, req.shipping_address, req.notes, req.shipping_phone,
        req.customer_name, req.customer_email, req.promotion_id,
    )
    return {"status_code": 201, "message": "Tạo đơn hàng thành công", "data": _to_order_response(o)}


@router.post("/{order_id}/status")
def update_status(order_id: int, status: str = Query(...), db: Session = Depends(get_db),
                  _: User = Depends(require_staff)):
    o = order_crud.update_order_status(db, order_id, status)
    return {"status_code": 200, "message": "Cập nhật trạng thái thành công", "data": _to_order_response(o)}


@router.post("/{order_id}/cancel")
def cancel(order_id: int, db: Session = Depends(get_db), user: User = Depends(get_current_user)):
    if user.role.name != "ADMIN" and not order_crud.is_order_owner(db, order_id, user.id):
        from app.core.exceptions import ForbiddenException
        raise ForbiddenException()
    o = order_crud.cancel_order(db, order_id)
    return {"status_code": 200, "message": "Hủy đơn hàng thành công", "data": _to_order_response(o)}
