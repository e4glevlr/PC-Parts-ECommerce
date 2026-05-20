from typing import Optional

from fastapi import Depends, HTTPException, status
from fastapi.security import HTTPBearer, HTTPAuthorizationCredentials
from sqlalchemy.orm import Session

from app.core.security import decode_token
from app.db.session import get_db
from app.models import User, Token

security_scheme = HTTPBearer(auto_error=False)


def get_current_user(
    credentials: Optional[HTTPAuthorizationCredentials] = Depends(security_scheme),
    db: Session = Depends(get_db),
) -> User:
    if credentials is None:
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Yêu cầu xác thực")

    token_str = credentials.credentials
    payload = decode_token(token_str)
    if payload is None:
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Token không hợp lệ hoặc đã hết hạn")

    # Check token is not revoked in DB
    db_token = db.query(Token).filter(Token.token == token_str, Token.revoked == False, Token.expired == False).first()
    if db_token is None:
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Token đã bị thu hồi")

    user_id = payload.get("user_id")
    user = db.query(User).filter(User.id == user_id, User.is_active == True).first()
    if user is None:
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Người dùng không tồn tại hoặc đã bị khóa")

    return user


def get_optional_user(
    credentials: Optional[HTTPAuthorizationCredentials] = Depends(security_scheme),
    db: Session = Depends(get_db),
) -> Optional[User]:
    if credentials is None:
        return None
    try:
        return get_current_user(credentials, db)
    except HTTPException:
        return None


class RoleChecker:
    def __init__(self, allowed_roles: list[str]):
        self.allowed_roles = allowed_roles

    def __call__(self, user: User = Depends(get_current_user)) -> User:
        if user.role.name not in self.allowed_roles:
            raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Không có quyền truy cập")
        return user


require_admin = RoleChecker(["ADMIN"])
require_staff = RoleChecker(["ADMIN", "STAFF"])
require_customer = RoleChecker(["CUSTOMER"])
require_authenticated = RoleChecker(["ADMIN", "STAFF", "CUSTOMER"])
