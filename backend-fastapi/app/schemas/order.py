from datetime import datetime
from typing import Optional

from pydantic import BaseModel, ConfigDict, Field


# ── Request Schemas ────────────────────────────────────────────────────

class OrderRequest(BaseModel):
    shipping_address: str = Field(..., max_length=500)
    notes: Optional[str] = Field(None, max_length=1000)
    shipping_phone: Optional[str] = Field(None, max_length=20)
    customer_name: Optional[str] = Field(None, max_length=100)
    customer_email: Optional[str] = Field(None, max_length=100)
    promotion_id: Optional[int] = None


# ── Response Schemas ───────────────────────────────────────────────────

class OrderItemResponse(BaseModel):
    id: int
    product_id: int
    product_name: str
    quantity: int
    price: float
    primary_image_url: Optional[str] = None

    model_config = ConfigDict(from_attributes=True)


class OrderResponse(BaseModel):
    id: int
    order_code: str
    user_id: int
    customer_name: str
    customer_email: str
    total_amount: float
    discount_amount: float
    final_amount: float
    promotion_id: Optional[int] = None
    status: str
    payment_method: str
    shipping_address: str
    shipping_phone: Optional[str] = None
    notes: Optional[str] = None
    order_items: list[OrderItemResponse] = []
    created_at: Optional[datetime] = None
    updated_at: Optional[datetime] = None

    model_config = ConfigDict(from_attributes=True)
