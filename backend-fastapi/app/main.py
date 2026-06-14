import os

from fastapi import FastAPI, HTTPException, Request
from fastapi.exceptions import RequestValidationError
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
from fastapi.staticfiles import StaticFiles
from sqlalchemy.exc import IntegrityError

from app.api.v1.router import api_router
from app.core.config import get_settings

settings = get_settings()

app = FastAPI(
    title="PC Parts E-Commerce API",
    description="Backend API cho hệ thống thương mại điện tử linh kiện máy tính",
    version="2.0.0",
    docs_url="/swagger-ui",
    redoc_url="/redoc",
)

origins = settings.CORS_ALLOWED_ORIGINS.split(",") if settings.CORS_ALLOWED_ORIGINS != "*" else ["*"]
app.add_middleware(
    CORSMiddleware,
    allow_origins=origins,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


# Map các thông báo lỗi gốc của Pydantic sang tiếng Việt dễ hiểu
_VALIDATION_MESSAGES = {
    "field required": "Trường này là bắt buộc",
    "Field required": "Trường này là bắt buộc",
    "value is not a valid email address": "Email không hợp lệ",
    "value is not a valid integer": "Giá trị phải là số nguyên",
    "value is not a valid float": "Giá trị phải là số",
    "Input should be a valid integer": "Giá trị phải là số nguyên",
    "Input should be a valid number": "Giá trị phải là số",
    "Input should be a valid string": "Giá trị phải là chuỗi ký tự",
    "Input should be a valid boolean": "Giá trị phải là true/false",
    "ensure this value is greater than 0": "Giá trị phải lớn hơn 0",
}


def _humanize_validation_error(err: dict) -> tuple[str, str]:
    """Trả về (tên_trường, thông_báo_dễ_hiểu) cho một lỗi validation."""
    loc = [str(p) for p in err.get("loc", []) if p not in ("body", "query", "path")]
    field = ".".join(loc) if loc else "dữ liệu"
    raw_msg = err.get("msg", "Giá trị không hợp lệ")
    # Khớp theo tiền tố vì Pydantic v2 thường nối thêm chi tiết vào sau thông báo gốc
    msg = next(
        (vi for en, vi in _VALIDATION_MESSAGES.items() if raw_msg.lower().startswith(en.lower())),
        raw_msg,
    )
    return field, msg


@app.exception_handler(HTTPException)
async def http_exception_handler(request: Request, exc: HTTPException):
    # Đưa thông báo lỗi (detail) vào field `message` để frontend hiển thị đúng cho người dùng,
    # thay vì shape mặc định {"detail": ...} mà frontend không đọc tới.
    return JSONResponse(
        status_code=exc.status_code,
        content={"status_code": exc.status_code, "message": exc.detail, "data": None},
        headers=getattr(exc, "headers", None),
    )


@app.exception_handler(RequestValidationError)
async def validation_exception_handler(request: Request, exc: RequestValidationError):
    # Gom lỗi theo từng trường + tạo một câu tóm tắt dễ đọc cho người dùng
    field_errors: dict[str, list[str]] = {}
    summaries: list[str] = []
    for err in exc.errors():
        field, msg = _humanize_validation_error(err)
        field_errors.setdefault(field, []).append(msg)
        summaries.append(f"{field}: {msg}")

    summary = "; ".join(summaries[:3])
    if len(summaries) > 3:
        summary += f" (và {len(summaries) - 3} lỗi khác)"
    message = f"Dữ liệu không hợp lệ — {summary}" if summary else "Dữ liệu không hợp lệ"

    return JSONResponse(
        status_code=422,
        content={"status_code": 422, "message": message, "data": field_errors},
    )


@app.exception_handler(IntegrityError)
async def integrity_error_handler(request: Request, exc: IntegrityError):
    return JSONResponse(
        status_code=400,
        content={"status_code": 400,
                 "message": "Thông tin bị trùng hoặc không hợp lệ. Email, tên đăng nhập hoặc số điện thoại "
                            "có thể đã được sử dụng. Vui lòng kiểm tra lại.",
                 "data": None},
    )


@app.exception_handler(Exception)
async def generic_exception_handler(request: Request, exc: Exception):
    return JSONResponse(
        status_code=500,
        content={"status_code": 500,
                 "message": "Hệ thống đang gặp sự cố. Vui lòng thử lại sau ít phút.",
                 "data": str(exc)},
    )


app.include_router(api_router, prefix="/api/v1")

# Serve uploaded / product images as static files (self-hosted, same-origin)
os.makedirs(settings.FILE_STORAGE_LOCATION, exist_ok=True)
app.mount("/images", StaticFiles(directory=settings.FILE_STORAGE_LOCATION), name="images")


@app.get("/")
def root():
    return {"message": "PC Parts E-Commerce API v2.0 - FastAPI"}
