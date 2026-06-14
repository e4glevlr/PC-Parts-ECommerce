from fastapi import APIRouter, Depends
from sqlalchemy.orm import Session
from app.api.dependencies import require_customer
from app.crud import cart as cart_crud
from app.db.session import get_db
from app.models import User, Cart
from app.schemas.cart import CartItemRequest, GuestCartMergeRequest, CartResponse, CartItemResponse

router = APIRouter()


def _to_cart_response(c: Cart) -> dict:
    items = []
    total_amount = 0.0
    total_items = 0
    for ci in (c.cart_items or []):
        p = ci.product
        price = float(p.price) if p else 0
        sub_total = price * ci.quantity
        items.append(CartItemResponse(
            id=ci.id, product_id=ci.product_id,
            product_name=p.name if p else "?", product_price=price,
            quantity=ci.quantity, sub_total=sub_total,
            product_image_url=p.primary_image_url if p else None,
            is_product_active=p.is_active if p else False,
            created_at=ci.created_at, updated_at=ci.updated_at,
        ).model_dump())
        total_amount += sub_total
        total_items += ci.quantity
    return CartResponse(
        id=c.id, user_id=c.user_id, cart_items=items,
        total_items=total_items, total_amount=total_amount,
        created_at=c.created_at, updated_at=c.updated_at,
    ).model_dump()


@router.get("")
def get_cart(db: Session = Depends(get_db), user: User = Depends(require_customer)):
    c = cart_crud.get_cart_by_user_id(db, user.id)
    return {"status_code": 200, "message": "Lấy giỏ hàng thành công", "data": _to_cart_response(c)}


@router.post("/items")
def add_item(req: CartItemRequest, db: Session = Depends(get_db), user: User = Depends(require_customer)):
    c = cart_crud.add_item_to_cart(db, user.id, req.product_id, req.quantity)
    return {"status_code": 200, "message": "Thêm sản phẩm thành công", "data": _to_cart_response(c)}


@router.put("/items/{item_id}")
def update_item(item_id: int, quantity: int, db: Session = Depends(get_db),
                user: User = Depends(require_customer)):
    c = cart_crud.update_cart_item(db, user.id, item_id, quantity)
    return {"status_code": 200, "message": "Cập nhật giỏ hàng thành công", "data": _to_cart_response(c)}


@router.delete("/items/{item_id}")
def remove_item(item_id: int, db: Session = Depends(get_db), user: User = Depends(require_customer)):
    c = cart_crud.remove_item_from_cart(db, user.id, item_id)
    return {"status_code": 200, "message": "Xóa sản phẩm thành công", "data": _to_cart_response(c)}


@router.delete("")
def clear(db: Session = Depends(get_db), user: User = Depends(require_customer)):
    cart_crud.clear_cart(db, user.id)
    return {"status_code": 200, "message": "Xóa toàn bộ giỏ hàng thành công"}


@router.post("/merge")
def merge(req: GuestCartMergeRequest, db: Session = Depends(get_db),
          user: User = Depends(require_customer)):
    guest_items = [{"product_id": gi.product_id, "quantity": gi.quantity} for gi in req.guest_cart_items]
    c = cart_crud.merge_guest_cart(db, user.id, guest_items)
    return {"status_code": 200, "message": "Hợp nhất giỏ hàng thành công", "data": _to_cart_response(c)}
