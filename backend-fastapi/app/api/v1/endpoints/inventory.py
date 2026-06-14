from datetime import datetime
from typing import Optional
from fastapi import APIRouter, Depends, Query
from sqlalchemy.orm import Session
from app.api.dependencies import require_staff, require_admin, get_current_user
from app.crud import product as product_crud
from app.db.session import get_db
from app.models import User, InventoryLog, Product
from app.core.exceptions import ResourceNotFoundException, BadRequestException
from app.schemas.misc import InventoryRequest

router = APIRouter()


def _product_stock(p: Product) -> dict:
    qty = p.quantity or 0
    out = qty <= 0
    return {
        "id": p.id, "name": p.name, "quantity": qty,
        "low_stock_threshold": p.low_stock_threshold,
        "is_low_stock": (not out) and qty <= p.low_stock_threshold,
        "is_out_of_stock": out,
        "category": {"id": p.category.id, "name": p.category.name} if p.category else None,
        "updated_at": str(p.updated_at) if p.updated_at else None,
        "price": float(p.price),
    }


@router.get("/logs")
def get_all_logs(page: int = 0, size: int = 20, change_type: Optional[str] = None,
                 date_from: Optional[str] = None, date_to: Optional[str] = None,
                 search: Optional[str] = None, db: Session = Depends(get_db),
                 _: User = Depends(require_staff)):
    query = db.query(InventoryLog)
    if change_type:
        query = query.filter(InventoryLog.change_type == change_type.upper())
    if date_from:
        query = query.filter(InventoryLog.created_at >= date_from)
    if date_to:
        query = query.filter(InventoryLog.created_at <= date_to)
    if search:
        kw = f"%{search}%"
        query = query.join(Product, InventoryLog.product_id == Product.id).filter(
            Product.name.ilike(kw) | InventoryLog.reason.ilike(kw))
    total = query.count()
    items = query.order_by(InventoryLog.created_at.desc()).offset(page * size).limit(size).all()
    tp = max((total + size - 1) // size, 1)
    return {"status_code": 200, "message": "OK", "data": {
        "content": [_fmt(il) for il in items], "page": page, "size": size,
        "total_elements": total, "total_pages": tp, "first": page == 0, "last": page >= tp - 1,
    }}


@router.get("/low-stock")
def get_low_stock(threshold: Optional[int] = None, db: Session = Depends(get_db),
                  _: User = Depends(require_staff)):
    query = db.query(Product).filter(Product.is_active == True, Product.quantity > 0)
    if threshold is not None:
        query = query.filter(Product.quantity <= threshold)
    else:
        query = query.filter(Product.quantity <= Product.low_stock_threshold)
    products = query.order_by(Product.quantity.asc()).all()
    out_count = db.query(Product).filter(Product.is_active == True, Product.quantity <= 0).count()
    total = db.query(Product).filter(Product.is_active == True).count()
    return {"status_code": 200, "message": "OK", "data": {
        "total_products": total, "low_stock_products": len(products),
        "out_of_stock_products": out_count,
        "products": [_product_stock(p) for p in products],
    }}


@router.get("/out-of-stock")
def get_out_of_stock(db: Session = Depends(get_db), _: User = Depends(require_staff)):
    products = db.query(Product).filter(Product.is_active == True, Product.quantity <= 0).all()
    return {"status_code": 200, "message": "OK", "data": [_product_stock(p) for p in products]}


@router.post("/products/{product_id}/adjust")
def adjust_inventory(product_id: int, req: InventoryRequest, db: Session = Depends(get_db),
                     user: User = Depends(require_staff)):
    product = product_crud.get_product_by_id(db, product_id)
    if req.change_type == "IN":
        new_qty = product.quantity + req.quantity
    else:
        new_qty = product.quantity - req.quantity
        if new_qty < 0:
            raise BadRequestException("Không đủ hàng trong kho")
    product_crud.update_stock(db, product_id, new_qty, req.reason, user.id)
    db.refresh(product)
    return {"status_code": 200, "message": "Điều chỉnh tồn kho thành công", "data": _product_stock(product)}


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
