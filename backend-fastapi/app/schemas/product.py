from datetime import datetime
from typing import Optional

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
