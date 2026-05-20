from typing import Optional
from fastapi import APIRouter, Depends
from sqlalchemy.orm import Session
from app.api.dependencies import require_admin
from app.db.session import get_db
from app.models import User, Category
from app.core.exceptions import ResourceNotFoundException, BadRequestException

router = APIRouter()


@router.get("")
def get_all(db: Session = Depends(get_db)):
    cats = db.query(Category).filter(Category.is_active == True).all()
    return {"status_code": 200, "message": "OK", "data": [_fmt(c) for c in cats]}


@router.get("/{cat_id}")
def get_one(cat_id: int, db: Session = Depends(get_db)):
    c = db.query(Category).filter(Category.id == cat_id).first()
    if not c:
        raise ResourceNotFoundException("Danh mục", "id", cat_id)
    return {"status_code": 200, "message": "OK", "data": _fmt(c)}


@router.post("", status_code=201)
def create(name: str, description: Optional[str] = None, parent_category_id: Optional[int] = None,
           db: Session = Depends(get_db), _: User = Depends(require_admin)):
    c = Category(name=name, description=description, parent_category_id=parent_category_id, is_active=True)
    db.add(c)
    db.commit()
    db.refresh(c)
    return {"status_code": 201, "message": "Tạo danh mục thành công", "data": _fmt(c)}


@router.put("/{cat_id}")
def update(cat_id: int, name: str, description: Optional[str] = None,
           db: Session = Depends(get_db), _: User = Depends(require_admin)):
    c = db.query(Category).filter(Category.id == cat_id).first()
    if not c:
        raise ResourceNotFoundException("Danh mục", "id", cat_id)
    c.name = name
    c.description = description
    db.commit()
    db.refresh(c)
    return {"status_code": 200, "message": "Cập nhật danh mục thành công", "data": _fmt(c)}


@router.delete("/{cat_id}")
def delete(cat_id: int, db: Session = Depends(get_db), _: User = Depends(require_admin)):
    c = db.query(Category).filter(Category.id == cat_id).first()
    if not c:
        raise ResourceNotFoundException("Danh mục", "id", cat_id)
    c.is_active = False
    db.commit()
    return {"status_code": 200, "message": "Xóa danh mục thành công"}


def _fmt(c: Category) -> dict:
    return {
        "id": c.id, "name": c.name, "description": c.description,
        "parent_category_id": c.parent_category_id, "is_active": c.is_active,
        "created_at": str(c.created_at) if c.created_at else None,
        "updated_at": str(c.updated_at) if c.updated_at else None,
    }
