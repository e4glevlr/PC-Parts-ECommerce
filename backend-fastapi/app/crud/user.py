import re
from datetime import datetime
from typing import Optional

from sqlalchemy import or_, func
from sqlalchemy.orm import Session

from app.core.security import hash_password, verify_password, create_access_token, create_refresh_token
from app.core.exceptions import BadRequestException, ResourceNotFoundException
from app.models import User, Role, Token


def _normalize_phone(phone: Optional[str]) -> Optional[str]:
    if not phone:
        return None
    p = phone.strip()
    p = re.sub(r"[\s-]", "", p)
    if p.startswith("+84"):
        p = "0" + p[3:]
    if p.startswith("84") and len(p) > 2 and p[2] != "0":
        p = "0" + p[2:]
    return p


def get_user_by_id(db: Session, user_id: int) -> User:
    user = db.query(User).filter(User.id == user_id).first()
    if not user:
        raise ResourceNotFoundException("Người dùng", "id", user_id)
    return user


def get_user_by_username(db: Session, username: str) -> Optional[User]:
    return db.query(User).filter(User.username == username).first()


def get_user_by_identifier(db: Session, identifier: str) -> Optional[User]:
    return (
        db.query(User)
        .filter(or_(User.username == identifier, User.email == identifier, User.phone == identifier))
        .first()
    )


def get_all_users(db: Session, page: int, size: int, search: Optional[str] = None):
    query = db.query(User)
    if search:
        kw = f"%{search}%"
        query = query.filter(
            or_(
                User.username.ilike(kw),
                User.email.ilike(kw),
                User.phone.ilike(kw),
                User.full_name.ilike(kw),
            )
        )
    total = query.count()
    items = query.offset(page * size).limit(size).all()
    return items, total


def get_users_by_role(db: Session, role_name: str) -> list[User]:
    role = db.query(Role).filter(func.upper(Role.name) == role_name.upper()).first()
    if not role:
        raise BadRequestException(f"Không tìm thấy vai trò: {role_name}")
    return db.query(User).filter(User.role_id == role.id).all()


def create_user(db: Session, username: str, email: str, password: str, full_name: str,
                phone: str, address: Optional[str], role_id: Optional[int] = None) -> User:
    phone = _normalize_phone(phone)

    if db.query(User).filter(User.username == username).first():
        raise BadRequestException("Username đã tồn tại")
    if db.query(User).filter(User.email == email).first():
        raise BadRequestException("Email đã tồn tại")
    if phone and db.query(User).filter(User.phone == phone).first():
        raise BadRequestException("Số điện thoại đã tồn tại")

    if role_id is None:
        role = db.query(Role).filter(Role.name == "CUSTOMER").first()
        if not role:
            raise BadRequestException("Default role CUSTOMER not found")
        role_id = role.id

    user = User(
        username=username, email=email, password=hash_password(password),
        full_name=full_name, phone=phone, address=address,
        role_id=role_id, is_active=True,
    )
    db.add(user)
    db.commit()
    db.refresh(user)
    return user


def update_user(db: Session, user_id: int, **kwargs) -> User:
    user = get_user_by_id(db, user_id)
    for k, v in kwargs.items():
        if v is not None and hasattr(user, k):
            setattr(user, k, v)
    user.updated_at = datetime.utcnow()
    db.commit()
    db.refresh(user)
    return user


def update_user_profile(db: Session, user: User, email: str, full_name: str,
                        phone: Optional[str], address: Optional[str]) -> User:
    phone = _normalize_phone(phone)
    if email != user.email and db.query(User).filter(User.email == email).first():
        raise BadRequestException("Email đã tồn tại")
    if phone and phone != user.phone and db.query(User).filter(User.phone == phone).first():
        raise BadRequestException("Số điện thoại đã tồn tại")

    user.email = email
    user.full_name = full_name
    user.phone = phone
    user.address = address
    user.updated_at = datetime.utcnow()
    db.commit()
    db.refresh(user)
    return user


def change_password(db: Session, user_id: int, old_password: str, new_password: str):
    user = get_user_by_id(db, user_id)
    if not user.is_active:
        raise BadRequestException("Tài khoản đã bị khóa")
    if not verify_password(old_password, user.password):
        raise BadRequestException("Mật khẩu hiện tại không đúng")
    user.password = hash_password(new_password)
    user.updated_at = datetime.utcnow()
    db.commit()


def soft_delete_user(db: Session, user_id: int):
    user = get_user_by_id(db, user_id)
    user.is_active = False
    user.updated_at = datetime.utcnow()
    db.commit()


def authenticate_user(db: Session, identifier: str, password: str) -> User:
    user = get_user_by_identifier(db, identifier)
    if not user:
        raise BadRequestException("Tên đăng nhập hoặc mật khẩu không đúng")
    if not user.is_active:
        raise BadRequestException("Tài khoản đã bị khóa")
    if not verify_password(password, user.password):
        raise BadRequestException("Tên đăng nhập hoặc mật khẩu không đúng")
    return user


def login(db: Session, identifier: str, password: str) -> dict:
    user = authenticate_user(db, identifier, password)
    access_token = create_access_token(user.id, user.username, user.role.name)
    refresh_token = create_refresh_token(user.id, user.username)

    # Save token to DB
    from app.core.config import get_settings
    settings = get_settings()
    from datetime import timedelta
    db_token = Token(
        user_id=user.id, token=access_token, token_type="ACCESS_TOKEN",
        expiration_date=datetime.utcnow() + timedelta(seconds=settings.JWT_EXPIRATION_SECONDS),
        revoked=False, expired=False,
    )
    db.add(db_token)
    db.commit()

    return {
        "access_token": access_token,
        "refresh_token": refresh_token,
        "token_type": "Bearer",
        "expires_in": settings.JWT_EXPIRATION_SECONDS,
        "user": user,
    }


def register(db: Session, username: str, email: str, password: str,
             full_name: str, phone: str, address: Optional[str] = None) -> dict:
    user = create_user(db, username, email, password, full_name, phone, address)
    access_token = create_access_token(user.id, user.username, user.role.name)
    refresh_token = create_refresh_token(user.id, user.username)

    from app.core.config import get_settings
    settings = get_settings()
    from datetime import timedelta
    db_token = Token(
        user_id=user.id, token=access_token, token_type="ACCESS_TOKEN",
        expiration_date=datetime.utcnow() + timedelta(seconds=settings.JWT_EXPIRATION_SECONDS),
        revoked=False, expired=False,
    )
    db.add(db_token)
    db.commit()

    return {
        "access_token": access_token,
        "refresh_token": refresh_token,
        "token_type": "Bearer",
        "expires_in": settings.JWT_EXPIRATION_SECONDS,
        "user": user,
    }


def logout(db: Session, token_str: str):
    db_token = db.query(Token).filter(Token.token == token_str).first()
    if db_token:
        db_token.expired = True
        db_token.revoked = True
        db.commit()
