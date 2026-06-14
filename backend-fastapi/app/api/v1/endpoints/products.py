from typing import Optional

from fastapi import APIRouter, Depends, Query
from sqlalchemy.orm import Session

from app.api.dependencies import require_admin, require_staff
from app.crud import product as product_crud
from app.db.session import get_db
from app.models import User, Product
from app.schemas.product import (ProductRequest, ProductWithImageUrlsRequest, ProductResponse,
                                  ProductImageResponse)

router = APIRouter()


def _to_product_response(p: Product) -> dict:
    return ProductResponse(
        id=p.id, name=p.name, description=p.description, price=float(p.price),
        quantity=p.quantity, low_stock_threshold=p.low_stock_threshold,
        category_id=p.category_id, category_name=p.category.name if p.category else None,
        specifications=p.specifications if isinstance(p.specifications, dict) else None,
        attributes=p.attributes if isinstance(p.attributes, dict) else None,
        images=[ProductImageResponse(id=img.id, file_path=img.image_url, is_primary=img.is_primary)
                for img in (p.images or [])],
        image_url=p.primary_image_url, is_active=p.is_active,
        is_low_stock=p.is_low_stock, created_at=p.created_at, updated_at=p.updated_at,
    ).model_dump()


def _paged(items, total, page, size):
    total_pages = max((total + size - 1) // size, 1)
    return {
        "content": [_to_product_response(p) for p in items], "page": page, "size": size,
        "total_elements": total, "total_pages": total_pages,
        "first": page == 0, "last": page >= total_pages - 1,
    }


@router.get("")
def get_products(
    page: int = Query(0, ge=0), size: int = Query(20, ge=1, le=100),
    category_id: Optional[list[int]] = Query(None), min_price: Optional[float] = Query(None),
    max_price: Optional[float] = Query(None), in_stock: Optional[bool] = Query(None),
    search: Optional[str] = Query(None), sort_by: Optional[str] = Query(None),
    sort_direction: str = Query("asc"), db: Session = Depends(get_db),
):
    items, total = product_crud.get_products(
        db, page, size, category_id, min_price, max_price,
        in_stock, search, sort_by, sort_direction,
    )
    return {"status_code": 200, "message": "Thành công", "data": _paged(items, total, page, size)}


@router.get("/management")
def get_management(category_id: Optional[int] = None, stock_status: Optional[str] = None,
                   search: Optional[str] = None, page: int = Query(0), size: int = Query(20),
                   db: Session = Depends(get_db), _: User = Depends(require_staff)):
    items, total = product_crud.get_products_for_management(db, page, size, category_id, stock_status, search)
    return {"status_code": 200, "message": "Thành công", "data": _paged(items, total, page, size)}


@router.get("/count")
def count_products(db: Session = Depends(get_db)):
    return {"status_code": 200, "message": "OK", "data": product_crud.count_active_products(db)}


@router.get("/search")
def search(keyword: str, page: int = 0, size: int = 20, db: Session = Depends(get_db)):
    items, total = product_crud.search_products(db, keyword, page, size)
    return {"status_code": 200, "message": "Thành công", "data": _paged(items, total, page, size)}


@router.get("/category/{category_id}")
def by_category(category_id: int, page: int = 0, size: int = 20, db: Session = Depends(get_db)):
    items, total = product_crud.get_products_by_category(db, category_id, page, size)
    return {"status_code": 200, "message": "Thành công", "data": _paged(items, total, page, size)}


@router.get("/{product_id}")
def get_product(product_id: int, db: Session = Depends(get_db)):
    p = product_crud.get_product_by_id(db, product_id)
    return {"status_code": 200, "message": "Thành công", "data": _to_product_response(p)}


@router.post("", status_code=201)
def create_product(req: ProductRequest, db: Session = Depends(get_db), _: User = Depends(require_admin)):
    p = product_crud.create_product(db, **req.model_dump())
    return {"status_code": 201, "message": "Tạo sản phẩm thành công", "data": _to_product_response(p)}


@router.post("/with-image-urls", status_code=201)
def create_with_urls(req: ProductWithImageUrlsRequest, db: Session = Depends(get_db),
                     _: User = Depends(require_admin)):
    data = req.model_dump()
    urls = data.pop("image_urls", None)
    p = product_crud.create_product_with_image_urls(db, image_urls=urls, **data)
    return {"status_code": 201, "message": "Tạo sản phẩm thành công", "data": _to_product_response(p)}


@router.put("/{product_id}")
def update_product(product_id: int, req: ProductRequest, db: Session = Depends(get_db),
                   _: User = Depends(require_admin)):
    p = product_crud.update_product(db, product_id, **req.model_dump())
    return {"status_code": 200, "message": "Cập nhật sản phẩm thành công", "data": _to_product_response(p)}


@router.delete("/{product_id}")
def delete_product(product_id: int, db: Session = Depends(get_db), _: User = Depends(require_admin)):
    product_crud.soft_delete_product(db, product_id)
    return {"status_code": 200, "message": "Xóa sản phẩm thành công"}
