from typing import Optional
from fastapi import APIRouter, Depends
from sqlalchemy.orm import Session
from app.api.dependencies import require_admin, require_staff
from app.db.session import get_db
from app.models import User, Category, AttributeDefinition
from app.core.exceptions import ResourceNotFoundException, BadRequestException
from app.schemas.misc import AttributeDefinitionRequest

router = APIRouter()


@router.get("")
def get_all(db: Session = Depends(get_db)):
    cats = db.query(Category).filter(Category.is_active == True).all()
    return {"status_code": 200, "message": "OK", "data": [_fmt(c) for c in cats]}


@router.get("/tree")
def get_tree(db: Session = Depends(get_db)):
    cats = db.query(Category).filter(Category.is_active == True).all()
    nodes = {c.id: {"id": c.id, "name": c.name, "slug": "", "children": []} for c in cats}
    roots = []
    for c in cats:
        node = nodes[c.id]
        parent = nodes.get(c.parent_category_id)
        if parent is not None:
            parent["children"].append(node)
        else:
            roots.append(node)
    return {"status_code": 200, "message": "OK", "data": roots}


@router.get("/{cat_id}")
def get_one(cat_id: int, db: Session = Depends(get_db)):
    c = db.query(Category).filter(Category.id == cat_id).first()
    if not c:
        raise ResourceNotFoundException("Danh mục", "id", cat_id)
    return {"status_code": 200, "message": "OK", "data": _fmt(c)}


@router.get("/{cat_id}/filters")
def get_filters(cat_id: int, db: Session = Depends(get_db)):
    defs = (
        db.query(AttributeDefinition)
        .filter(AttributeDefinition.category_id == cat_id, AttributeDefinition.is_active == True)
        .order_by(AttributeDefinition.sort_order.asc().nullslast(), AttributeDefinition.id.asc())
        .all()
    )
    return {"status_code": 200, "message": "OK", "data": [_fmt_attr(d) for d in defs]}


@router.post("/{cat_id}/attributes", status_code=201)
def create_attribute(cat_id: int, req: AttributeDefinitionRequest, db: Session = Depends(get_db),
                     _: User = Depends(require_admin)):
    if not db.query(Category).filter(Category.id == cat_id).first():
        raise ResourceNotFoundException("Danh mục", "id", cat_id)
    exists = (db.query(AttributeDefinition)
              .filter(AttributeDefinition.category_id == cat_id, AttributeDefinition.code == req.code)
              .first())
    if exists:
        raise BadRequestException(f"Thuộc tính '{req.code}' đã tồn tại trong danh mục này")
    d = AttributeDefinition(category_id=cat_id, **req.model_dump())
    db.add(d)
    db.commit()
    db.refresh(d)
    return {"status_code": 201, "message": "Tạo thuộc tính thành công", "data": _fmt_attr(d)}


@router.put("/{cat_id}/attributes/{attr_id}")
def update_attribute(cat_id: int, attr_id: int, req: AttributeDefinitionRequest,
                     db: Session = Depends(get_db), _: User = Depends(require_admin)):
    d = (db.query(AttributeDefinition)
         .filter(AttributeDefinition.id == attr_id, AttributeDefinition.category_id == cat_id)
         .first())
    if not d:
        raise ResourceNotFoundException("Thuộc tính", "id", attr_id)
    for k, v in req.model_dump().items():
        setattr(d, k, v)
    db.commit()
    db.refresh(d)
    return {"status_code": 200, "message": "Cập nhật thuộc tính thành công", "data": _fmt_attr(d)}


@router.delete("/{cat_id}/attributes/{attr_id}")
def delete_attribute(cat_id: int, attr_id: int, db: Session = Depends(get_db),
                     _: User = Depends(require_admin)):
    d = (db.query(AttributeDefinition)
         .filter(AttributeDefinition.id == attr_id, AttributeDefinition.category_id == cat_id)
         .first())
    if not d:
        raise ResourceNotFoundException("Thuộc tính", "id", attr_id)
    db.delete(d)
    db.commit()
    return {"status_code": 200, "message": "Xóa thuộc tính thành công"}


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


def _fmt_attr(d: AttributeDefinition) -> dict:
    return {
        "id": d.id, "category_id": d.category_id, "code": d.code,
        "display_name": d.display_name, "data_type": d.data_type,
        "input_type": d.input_type, "unit": d.unit, "sort_order": d.sort_order,
        "options": d.options, "is_active": d.is_active,
    }
