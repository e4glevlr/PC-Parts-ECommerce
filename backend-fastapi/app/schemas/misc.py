from datetime import datetime
from typing import Optional

from pydantic import BaseModel, ConfigDict, Field


# ── Comment Schemas ───────────────────────────────────────────────────

class CommentRequest(BaseModel):
    content: str = Field(..., min_length=1)
    parent_comment_id: Optional[int] = None


class CommentResponse(BaseModel):
    id: int
    user_id: int
    username: str
    full_name: str
    product_id: int
    content: str
    is_staff_reply: bool
    parent_comment_id: Optional[int] = None
    replies: list["CommentResponse"] = []
    created_at: Optional[datetime] = None
    updated_at: Optional[datetime] = None

    model_config = ConfigDict(from_attributes=True)


# ── Inventory Schemas ─────────────────────────────────────────────────

class InventoryRequest(BaseModel):
    change_type: str = Field(..., pattern=r"^(IN|OUT)$")
    quantity: int = Field(..., ge=1)
    reason: str
    performed_by_id: int


class InventoryLogResponse(BaseModel):
    id: int
    product_id: int
    product_name: str
    change_type: str
    quantity_change: int
    reason: Optional[str] = None
    performed_by: int
    performer_name: Optional[str] = None
    created_at: Optional[datetime] = None

    model_config = ConfigDict(from_attributes=True)


# ── Promotion Schemas ─────────────────────────────────────────────────

class PromotionRequest(BaseModel):
    name: str = Field(..., max_length=200)
    description: Optional[str] = None
    discount_type: str = Field(..., pattern=r"^(PERCENTAGE|FIXED_AMOUNT)$")
    discount_value: float = Field(..., gt=0)
    minimum_order_amount: Optional[float] = Field(0, ge=0)
    start_date: datetime
    end_date: datetime
    is_active: Optional[bool] = True

    def is_valid_date_range(self) -> bool:
        return self.end_date > self.start_date

    def is_valid_percentage(self) -> bool:
        if self.discount_type == "PERCENTAGE":
            return 0 < self.discount_value <= 100
        return True


class PromotionResponse(BaseModel):
    id: int
    name: str
    description: Optional[str] = None
    discount_type: str
    discount_value: float
    minimum_order_amount: float
    start_date: datetime
    end_date: datetime
    is_active: bool
    is_currently_active: bool = False
    is_expired: bool = False
    is_not_started: bool = False
    status: str = "INACTIVE"
    created_at: Optional[datetime] = None
    updated_at: Optional[datetime] = None

    model_config = ConfigDict(from_attributes=True)
