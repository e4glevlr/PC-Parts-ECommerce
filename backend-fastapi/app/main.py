from fastapi import FastAPI, Request
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
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


@app.exception_handler(IntegrityError)
async def integrity_error_handler(request: Request, exc: IntegrityError):
    return JSONResponse(
        status_code=400,
        content={"status_code": 400,
                 "message": "Dữ liệu vi phạm ràng buộc. Có thể trùng lặp các trường unique (email/username/phone)",
                 "data": None},
    )


@app.exception_handler(Exception)
async def generic_exception_handler(request: Request, exc: Exception):
    return JSONResponse(
        status_code=500,
        content={"status_code": 500, "message": "Đã xảy ra lỗi hệ thống", "data": str(exc)},
    )


app.include_router(api_router, prefix="/api/v1")


@app.get("/")
def root():
    return {"message": "PC Parts E-Commerce API v2.0 - FastAPI"}
