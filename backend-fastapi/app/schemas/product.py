from datetime import datetime
from typing import Optional, Any

from pydantic import BaseModel, ConfigDict, Field


# ── Request Schemas ────────────────────────────────────────────────────

class ProductRequest(BaseModel):
    name: str = Field(..., max_length=200)
    description: Optional[str] = None
    price: float = Field(..., gt=0)
    quantity: Optional[int] = Field(0, ge=0)
    low_stock_threshold: Optional[int] = Field(10, ge=0)
    category_id: int
    specifications: Optional[dict] = None
    attributes: Optional[dict] = None


class ProductWithImageUrlsRequest(ProductRequest):
    image_urls: Optional[list[str]] = None


# ── Response Schemas ───────────────────────────────────────────────────

class ProductImageResponse(BaseModel):
    id: int
    image_url: str
    is_primary: bool

    model_config = ConfigDict(from_attributes=True)


class ProductResponse(BaseModel):
    id: int
    name: str
    description: Optional[str] = None
    price: float
    quantity: int
    low_stock_threshold: int
    category_id: int
    category_name: Optional[str] = None
    specifications: Optional[dict] = None
    attributes: Optional[dict] = None
    images: list[ProductImageResponse] = []
    primary_image_url: Optional[str] = None
    is_active: bool
    is_low_stock: bool = False
    created_at: Optional[datetime] = None
    updated_at: Optional[datetime] = None

    model_config = ConfigDict(from_attributes=True)


# ── Category Schemas ──────────────────────────────────────────────────

class CategoryRequest(BaseModel):
    name: str = Field(..., max_length=100)
    description: Optional[str] = None
    parent_category_id: Optional[int] = None
    is_active: Optional[bool] = True


class CategoryResponse(BaseModel):
    id: int
    name: str
    description: Optional[str] = None
    parent_category_id: Optional[int] = None
    is_active: bool
    created_at: Optional[datetime] = None
    updated_at: Optional[datetime] = None

    model_config = ConfigDict(from_attributes=True)


# ── AttributeDefinition Schemas ──────────────────────────────────────

class AttributeDefinitionRequest(BaseModel):
    code: str = Field(..., max_length=100, pattern=r"^[a-zA-Z0-9_-]+$")
    display_name: str = Field(..., max_length=200)
    data_type: str = Field(..., max_length=20)
    input_type: str = Field(..., max_length=30)
    unit: Optional[str] = Field(None, max_length=50)
    sort_order: Optional[int] = None
    options: Optional[Any] = None
    is_active: Optional[bool] = True


class AttributeDefinitionResponse(BaseModel):
    id: int
    category_id: int
    code: str
    display_name: str
    data_type: str
    input_type: str
    unit: Optional[str] = None
    sort_order: Optional[int] = None
    options: Optional[Any] = None
    is_active: bool
    created_at: Optional[datetime] = None
    updated_at: Optional[datetime] = None

    model_config = ConfigDict(from_attributes=True)
