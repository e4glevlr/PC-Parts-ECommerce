from datetime import datetime
from typing import Optional

from pydantic import BaseModel, ConfigDict, EmailStr, Field


# ── Request Schemas ────────────────────────────────────────────────────

class LoginRequest(BaseModel):
    identifier: str = Field(..., min_length=1, description="Username, email, hoặc số điện thoại")
    password: str = Field(..., min_length=1)


class RegisterRequest(BaseModel):
    username: str = Field(..., min_length=3, max_length=50)
    password: str = Field(..., min_length=6)
    email: EmailStr
    full_name: str = Field(..., max_length=100)
    phone: str = Field(..., max_length=20)
    address: Optional[str] = None


class UserRequest(BaseModel):
    username: str = Field(..., min_length=3, max_length=50)
    email: EmailStr
    password: Optional[str] = Field(None, min_length=6)
    full_name: str = Field(..., max_length=100)
    phone: str = Field(..., max_length=20)
    address: Optional[str] = None
    role_id: Optional[int] = None


class ProfileUpdateRequest(BaseModel):
    email: EmailStr
    full_name: str = Field(..., max_length=100)
    phone: Optional[str] = Field(None, max_length=15)
    address: Optional[str] = Field(None, max_length=500)


class ChangePasswordRequest(BaseModel):
    old_password: str
    new_password: str = Field(..., min_length=6)


# ── Response Schemas ───────────────────────────────────────────────────

class UserResponse(BaseModel):
    id: int
    username: str
    email: str
    full_name: str
    phone: Optional[str] = None
    address: Optional[str] = None
    role: str
    is_active: bool
    created_at: Optional[datetime] = None
    updated_at: Optional[datetime] = None

    model_config = ConfigDict(from_attributes=True)


class AuthResponse(BaseModel):
    access_token: str
    refresh_token: str
    token_type: str = "Bearer"
    expires_in: int
    user: UserResponse
