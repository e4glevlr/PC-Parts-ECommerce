from pydantic_settings import BaseSettings
from functools import lru_cache


class Settings(BaseSettings):
    DATABASE_URL: str = "postgresql://your_username:your_password@localhost:5432/pc_shop_database"

    JWT_SECRET: str = "your_jwt_secret_key_here_change_in_production"
    JWT_EXPIRATION_SECONDS: int = 2592000  # 30 days
    JWT_REFRESH_EXPIRATION_SECONDS: int = 7776000  # 90 days
    JWT_ALGORITHM: str = "HS256"

    CORS_ALLOWED_ORIGINS: str = "*"

    FILE_STORAGE_LOCATION: str = "images"
    FILE_MAX_SIZE_MB: int = 10

    DEFAULT_LOW_STOCK_THRESHOLD: int = 10

    SERVER_PORT: int = 8000
    TIMEZONE: str = "Asia/Ho_Chi_Minh"

    class Config:
        env_file = ".env"
        case_sensitive = True


@lru_cache()
def get_settings() -> Settings:
    return Settings()
