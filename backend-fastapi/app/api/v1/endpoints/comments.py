from datetime import datetime
from typing import Optional
from fastapi import APIRouter, Depends, Query
from sqlalchemy.orm import Session
from app.api.dependencies import get_current_user, require_admin
from app.db.session import get_db
from app.models import User, Comment, Product
from app.core.exceptions import ResourceNotFoundException, BadRequestException, ForbiddenException

router = APIRouter()


@router.get("/product/{product_id}")
def get_comments(product_id: int, page: int = 0, size: int = 20, db: Session = Depends(get_db)):
    query = db.query(Comment).filter(Comment.product_id == product_id, Comment.parent_comment_id == None)
    total = query.count()
    items = query.order_by(Comment.created_at.desc()).offset(page * size).limit(size).all()
    return {"status_code": 200, "message": "OK", "data": {
        "content": [_fmt(c, db) for c in items], "total_elements": total,
    }}


@router.post("/product/{product_id}", status_code=201)
def create_comment(product_id: int, content: str, parent_comment_id: Optional[int] = None,
                   db: Session = Depends(get_db), user: User = Depends(get_current_user)):
    if not db.query(Product).filter(Product.id == product_id).first():
        raise ResourceNotFoundException("Sản phẩm", "id", product_id)
    is_staff = user.role.name in ("ADMIN", "STAFF")
    c = Comment(user_id=user.id, product_id=product_id, content=content,
                parent_comment_id=parent_comment_id, is_staff_reply=is_staff)
    db.add(c)
    db.commit()
    db.refresh(c)
    return {"status_code": 201, "message": "Thêm bình luận thành công", "data": _fmt(c, db)}


@router.put("/{comment_id}")
def update_comment(comment_id: int, content: str, db: Session = Depends(get_db),
                   user: User = Depends(get_current_user)):
    c = db.query(Comment).filter(Comment.id == comment_id).first()
    if not c:
        raise ResourceNotFoundException("Bình luận", "id", comment_id)
    if c.user_id != user.id and user.role.name != "ADMIN":
        raise ForbiddenException()
    c.content = content
    c.updated_at = datetime.utcnow()
    db.commit()
    db.refresh(c)
    return {"status_code": 200, "message": "Cập nhật bình luận thành công", "data": _fmt(c, db)}


@router.delete("/{comment_id}")
def delete_comment(comment_id: int, db: Session = Depends(get_db), user: User = Depends(get_current_user)):
    c = db.query(Comment).filter(Comment.id == comment_id).first()
    if not c:
        raise ResourceNotFoundException("Bình luận", "id", comment_id)
    if c.user_id != user.id and user.role.name != "ADMIN":
        raise ForbiddenException()
    db.delete(c)
    db.commit()
    return {"status_code": 200, "message": "Xóa bình luận thành công"}


def _fmt(c: Comment, db: Session) -> dict:
    replies = db.query(Comment).filter(Comment.parent_comment_id == c.id).order_by(Comment.created_at.asc()).all()
    return {
        "id": c.id, "user_id": c.user_id, "username": c.user.username, "full_name": c.user.full_name,
        "product_id": c.product_id, "content": c.content, "is_staff_reply": c.is_staff_reply,
        "parent_comment_id": c.parent_comment_id,
        "replies": [_fmt(r, db) for r in replies],
        "created_at": str(c.created_at) if c.created_at else None,
        "updated_at": str(c.updated_at) if c.updated_at else None,
    }
