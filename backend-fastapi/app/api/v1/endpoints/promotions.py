from datetime import datetime
from typing import Optional
from fastapi import APIRouter, Depends, Query
from sqlalchemy.orm import Session
from app.api.dependencies import require_admin
from app.db.session import get_db
from app.models import User, Promotion
from app.core.exceptions import ResourceNotFoundException, BadRequestException
from app.schemas.misc import PromotionRequest

router = APIRouter()


def _status(p: Promotion) -> dict:
    now = datetime.utcnow()
    is_active = p.is_active and p.start_date <= now <= p.end_date
    expired = now > p.end_date
    not_started = now < p.start_date
    if not p.is_active:
        st = "INACTIVE"
    elif not_started:
        st = "NOT_STARTED"
    elif expired:
        st = "EXPIRED"
    else:
        st = "ACTIVE"
    return {
        "id": p.id, "name": p.name, "description": p.description,
        "discount_type": p.discount_type, "discount_value": float(p.discount_value),
        "minimum_order_amount": float(p.minimum_order_amount),
        "start_date": str(p.start_date), "end_date": str(p.end_date),
        "is_active": p.is_active, "is_currently_active": is_active,
        "is_expired": expired, "is_not_started": not_started, "status": st,
        "created_at": str(p.created_at) if p.created_at else None,
        "updated_at": str(p.updated_at) if p.updated_at else None,
    }


@router.get("")
def get_all(page: int = 0, size: int = 20, db: Session = Depends(get_db)):
    query = db.query(Promotion).order_by(Promotion.created_at.desc())
    total = query.count()
    items = query.offset(page * size).limit(size).all()
    tp = max((total + size - 1) // size, 1)
    return {"status_code": 200, "message": "OK", "data": {
        "content": [_status(p) for p in items], "page": page, "size": size,
        "total_elements": total, "total_pages": tp, "first": page == 0, "last": page >= tp - 1,
    }}


@router.get("/active")
def get_active(db: Session = Depends(get_db)):
    now = datetime.utcnow()
    items = db.query(Promotion).filter(Promotion.is_active == True, Promotion.start_date <= now, Promotion.end_date >= now).all()
    return {"status_code": 200, "message": "OK", "data": [_status(p) for p in items]}


@router.get("/applicable")
def get_applicable(price: float = Query(0, ge=0), db: Session = Depends(get_db)):
    now = datetime.utcnow()
    items = db.query(Promotion).filter(
        Promotion.is_active == True, Promotion.start_date <= now, Promotion.end_date >= now,
        Promotion.minimum_order_amount <= price,
    ).all()
    return {"status_code": 200, "message": "OK", "data": [_status(p) for p in items]}


def _compute_discount(p: Promotion, original_price: float) -> float:
    if p.discount_type == "PERCENTAGE":
        discount = original_price * float(p.discount_value) / 100
    else:  # FIXED_AMOUNT
        discount = float(p.discount_value)
    return round(min(discount, original_price), 2)


@router.get("/{promo_id}/calculate-discount")
def calculate_discount(promo_id: int, originalPrice: float = Query(..., ge=0),
                       db: Session = Depends(get_db)):
    p = db.query(Promotion).filter(Promotion.id == promo_id).first()
    if not p:
        raise ResourceNotFoundException("Khuyến mãi", "id", promo_id)
    discount = _compute_discount(p, originalPrice)
    return {"status_code": 200, "message": "OK", "data": {
        "discount_amount": discount, "final_price": round(originalPrice - discount, 2),
    }}


@router.get("/{promo_id}")
def get_one(promo_id: int, db: Session = Depends(get_db)):
    p = db.query(Promotion).filter(Promotion.id == promo_id).first()
    if not p:
        raise ResourceNotFoundException("Khuyến mãi", "id", promo_id)
    return {"status_code": 200, "message": "OK", "data": _status(p)}


@router.post("", status_code=201)
def create(req: PromotionRequest, db: Session = Depends(get_db), _: User = Depends(require_admin)):
    if not req.is_valid_date_range():
        raise BadRequestException("Ngày kết thúc phải sau ngày bắt đầu")
    if not req.is_valid_percentage():
        raise BadRequestException("Phần trăm giảm giá phải từ 0 đến 100")
    p = Promotion(**req.model_dump())
    db.add(p)
    db.commit()
    db.refresh(p)
    return {"status_code": 201, "message": "Tạo khuyến mãi thành công", "data": _status(p)}


@router.put("/{promo_id}")
def update(promo_id: int, req: PromotionRequest, db: Session = Depends(get_db),
           _: User = Depends(require_admin)):
    p = db.query(Promotion).filter(Promotion.id == promo_id).first()
    if not p:
        raise ResourceNotFoundException("Khuyến mãi", "id", promo_id)
    if not req.is_valid_date_range():
        raise BadRequestException("Ngày kết thúc phải sau ngày bắt đầu")
    for k, v in req.model_dump().items():
        setattr(p, k, v)
    p.updated_at = datetime.utcnow()
    db.commit()
    db.refresh(p)
    return {"status_code": 200, "message": "Cập nhật khuyến mãi thành công", "data": _status(p)}


@router.delete("/{promo_id}")
def delete(promo_id: int, db: Session = Depends(get_db), _: User = Depends(require_admin)):
    p = db.query(Promotion).filter(Promotion.id == promo_id).first()
    if not p:
        raise ResourceNotFoundException("Khuyến mãi", "id", promo_id)
    p.is_active = False
    db.commit()
    return {"status_code": 200, "message": "Xóa khuyến mãi thành công"}


@router.put("/{promo_id}/activate")
def activate(promo_id: int, db: Session = Depends(get_db), _: User = Depends(require_admin)):
    p = db.query(Promotion).filter(Promotion.id == promo_id).first()
    if not p:
        raise ResourceNotFoundException("Khuyến mãi", "id", promo_id)
    p.is_active = True
    db.commit()
    db.refresh(p)
    return {"status_code": 200, "message": "Kích hoạt thành công", "data": _status(p)}


@router.put("/{promo_id}/deactivate")
def deactivate(promo_id: int, db: Session = Depends(get_db), _: User = Depends(require_admin)):
    p = db.query(Promotion).filter(Promotion.id == promo_id).first()
    if not p:
        raise ResourceNotFoundException("Khuyến mãi", "id", promo_id)
    p.is_active = False
    db.commit()
    db.refresh(p)
    return {"status_code": 200, "message": "Vô hiệu hóa thành công", "data": _status(p)}
