from datetime import datetime
from decimal import Decimal
from typing import Optional

from sqlalchemy import or_, func, and_, cast, Numeric, text
from sqlalchemy.orm import Session

from app.core.exceptions import ResourceNotFoundException, BadRequestException
from app.models import Product, Category, ProductImage, InventoryLog, User, AttributeDefinition


def get_product_by_id(db: Session, product_id: int) -> Product:
    product = db.query(Product).filter(Product.id == product_id).first()
    if not product:
        raise ResourceNotFoundException("Sản phẩm", "id", product_id)
    return product


def get_products(db: Session, page: int, size: int, category_ids: Optional[list[int]] = None,
                 min_price: Optional[float] = None, max_price: Optional[float] = None,
                 in_stock: Optional[bool] = None, search: Optional[str] = None,
                 sort_by: Optional[str] = None, sort_direction: str = "asc",
                 attr_equals: Optional[dict] = None, attr_min: Optional[dict] = None,
                 attr_max: Optional[dict] = None):
    query = db.query(Product).filter(Product.is_active == True)

    if category_ids:
        query = query.filter(Product.category_id.in_(category_ids))
    if min_price is not None:
        query = query.filter(Product.price >= min_price)
    if max_price is not None:
        query = query.filter(Product.price <= max_price)
    if in_stock is True:
        query = query.filter(Product.quantity > 0)
    if search:
        kw = f"%{search}%"
        query = query.filter(or_(Product.name.ilike(kw), Product.description.ilike(kw)))

    # Dynamic attribute filtering via JSONB
    if attr_equals:
        for key, values in attr_equals.items():
            if values:
                conditions = [Product.attributes[key].as_string() == v for v in values]
                query = query.filter(or_(*conditions))
    if attr_min:
        for key, min_val in attr_min.items():
            query = query.filter(cast(Product.attributes[key].as_string(), Numeric) >= min_val)
    if attr_max:
        for key, max_val in attr_max.items():
            query = query.filter(cast(Product.attributes[key].as_string(), Numeric) <= max_val)

    # Sorting
    if sort_by == "price":
        query = query.order_by(Product.price.desc() if sort_direction == "desc" else Product.price.asc())
    elif sort_by == "name":
        query = query.order_by(Product.name.desc() if sort_direction == "desc" else Product.name.asc())
    elif sort_by == "created_at":
        query = query.order_by(Product.created_at.desc() if sort_direction == "desc" else Product.created_at.asc())
    else:
        query = query.order_by(Product.created_at.desc())

    total = query.count()
    items = query.offset(page * size).limit(size).all()
    return items, total


def get_products_for_management(db: Session, page: int, size: int, category_id: Optional[int] = None,
                                stock_status: Optional[str] = None, search: Optional[str] = None):
    query = db.query(Product)

    if category_id:
        query = query.filter(Product.category_id == category_id)
    if stock_status:
        ss = stock_status.lower()
        if ss == "in_stock":
            query = query.filter(Product.quantity > 0)
        elif ss == "out_of_stock":
            query = query.filter(Product.quantity == 0)
        elif ss == "low_stock":
            query = query.filter(Product.quantity > 0, Product.quantity <= Product.low_stock_threshold)
    if search:
        kw = f"%{search}%"
        query = query.filter(or_(Product.name.ilike(kw), Product.description.ilike(kw)))

    total = query.count()
    items = query.order_by(Product.created_at.desc()).offset(page * size).limit(size).all()
    return items, total


def get_products_by_category(db: Session, category_id: int, page: int, size: int):
    query = db.query(Product).filter(Product.category_id == category_id, Product.is_active == True)
    total = query.count()
    items = query.offset(page * size).limit(size).all()
    return items, total


def search_products(db: Session, keyword: str, page: int, size: int):
    kw = f"%{keyword}%"
    query = db.query(Product).filter(Product.is_active == True, or_(Product.name.ilike(kw), Product.description.ilike(kw)))
    total = query.count()
    items = query.offset(page * size).limit(size).all()
    return items, total


def count_active_products(db: Session) -> int:
    return db.query(Product).filter(Product.is_active == True).count()


def create_product(db: Session, **kwargs) -> Product:
    category_id = kwargs.get("category_id")
    category = db.query(Category).filter(Category.id == category_id).first()
    if not category:
        raise ResourceNotFoundException("Danh mục", "id", category_id)
    if not category.is_active:
        raise BadRequestException("Danh mục hiện không hoạt động")

    product = Product(
        name=kwargs["name"], description=kwargs.get("description"),
        price=kwargs["price"], quantity=kwargs.get("quantity", 0),
        low_stock_threshold=kwargs.get("low_stock_threshold", 10),
        category_id=category_id, specifications=kwargs.get("specifications"),
        attributes=kwargs.get("attributes"), is_active=True,
    )
    db.add(product)
    db.commit()
    db.refresh(product)
    return product


def create_product_with_image_urls(db: Session, image_urls: Optional[list[str]] = None, **kwargs) -> Product:
    product = create_product(db, **kwargs)
    if image_urls:
        for i, url in enumerate(image_urls):
            img = ProductImage(product_id=product.id, image_url=url, is_primary=(i == 0))
            db.add(img)
        db.commit()
        db.refresh(product)
    return product


def update_product(db: Session, product_id: int, **kwargs) -> Product:
    product = get_product_by_id(db, product_id)
    category_id = kwargs.get("category_id")
    if category_id:
        category = db.query(Category).filter(Category.id == category_id).first()
        if not category:
            raise ResourceNotFoundException("Danh mục", "id", category_id)

    for k, v in kwargs.items():
        if v is not None and hasattr(product, k):
            setattr(product, k, v)
    product.updated_at = datetime.utcnow()
    db.commit()
    db.refresh(product)
    return product


def soft_delete_product(db: Session, product_id: int):
    product = get_product_by_id(db, product_id)
    product.is_active = False
    db.commit()


def update_stock(db: Session, product_id: int, new_quantity: int, reason: str, performed_by: int):
    product = get_product_by_id(db, product_id)
    user = db.query(User).filter(User.id == performed_by).first()
    if not user:
        raise ResourceNotFoundException("Người dùng", "id", performed_by)

    old_qty = product.quantity
    change = new_quantity - old_qty
    change_type = "IN" if change > 0 else "OUT"

    product.quantity = new_quantity
    log = InventoryLog(
        product_id=product_id, change_type=change_type,
        quantity_change=abs(change), reason=reason, performed_by=performed_by,
    )
    db.add(log)
    db.commit()
    db.refresh(product)
    return product


# ── Attribute Definitions ─────────────────────────────────────────────

def get_attribute_definitions(db: Session, category_id: int) -> list[AttributeDefinition]:
    return (
        db.query(AttributeDefinition)
        .filter(AttributeDefinition.category_id == category_id, AttributeDefinition.is_active == True)
        .order_by(func.coalesce(AttributeDefinition.sort_order, 9999))
        .all()
    )


def create_attribute_definition(db: Session, category_id: int, **kwargs) -> AttributeDefinition:
    category = db.query(Category).filter(Category.id == category_id).first()
    if not category:
        raise ResourceNotFoundException("Danh mục", "id", category_id)
    if db.query(AttributeDefinition).filter(
        AttributeDefinition.category_id == category_id,
        func.lower(AttributeDefinition.code) == kwargs["code"].lower()
    ).first():
        raise BadRequestException(f"Attribute code '{kwargs['code']}' đã tồn tại cho danh mục này")

    attr_def = AttributeDefinition(category_id=category_id, **kwargs)
    db.add(attr_def)
    db.commit()
    db.refresh(attr_def)
    return attr_def


def update_attribute_definition(db: Session, attr_id: int, category_id: int, **kwargs) -> AttributeDefinition:
    attr_def = db.query(AttributeDefinition).filter(
        AttributeDefinition.id == attr_id, AttributeDefinition.category_id == category_id
    ).first()
    if not attr_def:
        raise ResourceNotFoundException("Thuộc tính", "id", attr_id)
    for k, v in kwargs.items():
        if v is not None and hasattr(attr_def, k):
            setattr(attr_def, k, v)
    attr_def.updated_at = datetime.utcnow()
    db.commit()
    db.refresh(attr_def)
    return attr_def


def delete_attribute_definition(db: Session, attr_id: int, category_id: int):
    attr_def = db.query(AttributeDefinition).filter(
        AttributeDefinition.id == attr_id, AttributeDefinition.category_id == category_id
    ).first()
    if not attr_def:
        raise ResourceNotFoundException("Thuộc tính", "id", attr_id)
    attr_def.is_active = False
    db.commit()
