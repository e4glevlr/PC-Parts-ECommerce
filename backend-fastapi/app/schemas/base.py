from datetime import datetime
from typing import Optional

from pydantic import BaseModel, ConfigDict


class ApiResponse(BaseModel):
    status_code: int
    message: str
    data: Optional[dict | list | str | int | float | bool] = None

    model_config = ConfigDict(from_attributes=True)


class PagedResponse(BaseModel):
    content: list
    page: int
    size: int
    total_elements: int
    total_pages: int
    first: bool
    last: bool

    model_config = ConfigDict(from_attributes=True)
