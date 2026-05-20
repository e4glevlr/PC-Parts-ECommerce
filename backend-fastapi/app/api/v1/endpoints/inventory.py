from datetime import datetime
from typing import Optional
from fastapi import APIRouter, Depends, Query
from sqlalchemy.orm import Session
from app.api.dependencies import require_staff, require_admin
from app.crud import product as product_crud
from app.db.session import get_db
from app.models import User, InventoryLog, Product
from app.core.exceptions import ResourceNotFoundException
from app.schemas.misc import InventoryRequest

router = APIRouter()


@router.get("/{product_id}")
def get_logs(product_id: int, page: int = 0, size: int = 20,
             db: Session = Depends(get_db), _: User = Depends(require_staff)):
    query = db.query(InventoryLog).filter(InventoryLog.product_id == product_id).order_by(InventoryLog.created_at.desc())
    total = query.count()
    items = query.offset(page * size).limit(size).all()
    return {"status_code": 200, "message": "OK", "data": {
        "content": [_fmt(il) for il in items], "total_elements": total,
    }}


@router.post("/{product_id}")
def update_inventory(product_id: int, req: InventoryRequest, db: Session = Depends(get_db),
                     _: User = Depends(require_staff)):
    product = product_crud.get_product_by_id(db, product_id)
    if req.change_type == "IN":
        new_qty = product.quantity + req.quantity
    else:
        new_qty = product.quantity - req.quantity
        if new_qty < 0:
            from app.core.exceptions import BadRequestException
            raise BadRequestException("Không đủ hàng trong kho")
    product_crud.update_stock(db, product_id, new_qty, req.reason, req.performed_by_id)
    db.refresh(product)
    return {"status_code": 200, "message": "Cập nhật tồn kho thành công", "data": {
        "product_id": product_id, "new_quantity": product.quantity,
    }}


def _fmt(il: InventoryLog) -> dict:
    return {
        "id": il.id, "product_id": il.product_id,
        "product_name": il.product.name if il.product else None,
        "change_type": il.change_type, "quantity_change": il.quantity_change,
        "reason": il.reason, "performed_by": il.performed_by,
        "performer_name": il.performer.full_name if il.performer else None,
        "created_at": str(il.created_at) if il.created_at else None,
    }
