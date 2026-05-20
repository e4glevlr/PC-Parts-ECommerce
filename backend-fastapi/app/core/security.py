from datetime import datetime, timedelta, timezone
from typing import Optional

from jose import JWTError, jwt
from passlib.context import CryptContext

from app.core.config import get_settings

settings = get_settings()

pwd_context = CryptContext(schemes=["bcrypt"], deprecated="auto")


def hash_password(password: str) -> str:
    return pwd_context.hash(password)


def verify_password(plain: str, hashed: str) -> bool:
    return pwd_context.verify(plain, hashed)


def create_access_token(user_id: int, username: str, role: str) -> str:
    expire = datetime.now(timezone.utc) + timedelta(seconds=settings.JWT_EXPIRATION_SECONDS)
    payload = {
        "sub": username,
        "user_id": user_id,
        "role": role,
        "exp": expire,
        "type": "access",
    }
    return jwt.encode(payload, settings.JWT_SECRET, algorithm=settings.JWT_ALGORITHM)


def create_refresh_token(user_id: int, username: str) -> str:
    expire = datetime.now(timezone.utc) + timedelta(seconds=settings.JWT_REFRESH_EXPIRATION_SECONDS)
    payload = {
        "sub": username,
        "user_id": user_id,
        "exp": expire,
        "type": "refresh",
    }
    return jwt.encode(payload, settings.JWT_SECRET, algorithm=settings.JWT_ALGORITHM)


def decode_token(token: str) -> Optional[dict]:
    try:
        return jwt.decode(token, settings.JWT_SECRET, algorithms=[settings.JWT_ALGORITHM])
    except JWTError:
        return None


def extract_user_id(token: str) -> Optional[int]:
    payload = decode_token(token)
    if payload:
        return payload.get("user_id")
    return None


def extract_username(token: str) -> Optional[str]:
    payload = decode_token(token)
    if payload:
        return payload.get("sub")
    return None
