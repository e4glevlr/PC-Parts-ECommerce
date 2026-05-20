from datetime import datetime
from typing import Optional

from pydantic import BaseModel, ConfigDict, Field


# ── Request Schemas ────────────────────────────────────────────────────

class CartItemRequest(BaseModel):
    product_id: int
    quantity: int = Field(..., ge=1)


class GuestCartItem(BaseModel):
    product_id: int
    quantity: int = Field(..., ge=1)


class GuestCartMergeRequest(BaseModel):
    guest_cart_items: list[GuestCartItem]


# ── Response Schemas ───────────────────────────────────────────────────

class CartItemResponse(BaseModel):
    id: int
    product_id: int
    product_name: str
    product_price: float
    quantity: int
    primary_image_url: Optional[str] = None
    created_at: Optional[datetime] = None

    model_config = ConfigDict(from_attributes=True)


class CartResponse(BaseModel):
    id: int
    user_id: int
    items: list[CartItemResponse] = []
    total_items: int = 0
    total_price: float = 0
    created_at: Optional[datetime] = None
    updated_at: Optional[datetime] = None

    model_config = ConfigDict(from_attributes=True)
