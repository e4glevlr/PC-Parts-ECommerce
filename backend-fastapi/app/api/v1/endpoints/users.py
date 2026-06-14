from fastapi import APIRouter, Depends, Query
from sqlalchemy.orm import Session

from app.api.dependencies import get_current_user, require_admin, require_authenticated
from app.core.config import get_settings
from app.core.security import create_access_token, decode_token
from app.core.exceptions import UnauthorizedException
from app.crud import user as user_crud
from app.db.session import get_db
from app.models import User
from app.schemas.user import (
    LoginRequest, RegisterRequest, UserRequest, UserResponse, AuthResponse,
    ProfileUpdateRequest, ChangePasswordRequest,
)

router = APIRouter()


def _to_user_response(u: User) -> dict:
    return UserResponse(
        id=u.id, username=u.username, email=u.email, full_name=u.full_name,
        phone=u.phone, address=u.address, role=u.role.name,
        is_active=u.is_active, created_at=u.created_at, updated_at=u.updated_at,
    ).model_dump()


# ── Auth ──────────────────────────────────────────────────────────────

@router.post("/login")
def login(req: LoginRequest, db: Session = Depends(get_db)):
    result = user_crud.login(db, req.identifier, req.password)
    user = result["user"]
    return {
        "status_code": 200, "message": "Đăng nhập thành công",
        "data": AuthResponse(
            access_token=result["access_token"], refresh_token=result["refresh_token"],
            expires_in=result["expires_in"],
            user=UserResponse(id=user.id, username=user.username, email=user.email,
                              full_name=user.full_name, phone=user.phone, address=user.address,
                              role=user.role.name, is_active=user.is_active),
        ).model_dump(),
    }


@router.post("/logout")
def logout():
    # Stateless JWT: nothing to invalidate server-side; client clears its tokens.
    return {"status_code": 200, "message": "Đăng xuất thành công"}


@router.post("/refresh-token")
def refresh_token(refreshToken: str = Query(...), db: Session = Depends(get_db)):
    payload = decode_token(refreshToken)
    if not payload or payload.get("type") != "refresh":
        raise UnauthorizedException("Refresh token không hợp lệ")
    user = db.query(User).filter(User.id == payload.get("user_id")).first()
    if not user or not user.is_active:
        raise UnauthorizedException("Người dùng không tồn tại hoặc đã bị khóa")
    access_token = create_access_token(user.id, user.username, user.role.name)
    return {"status_code": 200, "message": "Làm mới token thành công", "data": {
        "access_token": access_token, "token_type": "Bearer",
        "expires_in": get_settings().JWT_EXPIRATION_SECONDS,
    }}


@router.post("/register", status_code=201)
def register(req: RegisterRequest, db: Session = Depends(get_db)):
    result = user_crud.register(db, req.username, req.email, req.password,
                                req.full_name, req.phone, req.address)
    user = result["user"]
    return {
        "status_code": 201, "message": "Đăng ký thành công",
        "data": AuthResponse(
            access_token=result["access_token"], refresh_token=result["refresh_token"],
            expires_in=result["expires_in"],
            user=UserResponse(id=user.id, username=user.username, email=user.email,
                              full_name=user.full_name, phone=user.phone, address=user.address,
                              role=user.role.name, is_active=user.is_active),
        ).model_dump(),
    }


# ── Profile ──────────────────────────────────────────────────────────

@router.get("/profile")
def get_profile(user: User = Depends(get_current_user)):
    return {"status_code": 200, "message": "Lấy profile thành công", "data": _to_user_response(user)}


@router.put("/profile")
def update_profile(req: ProfileUpdateRequest, db: Session = Depends(get_db),
                   user: User = Depends(get_current_user)):
    updated = user_crud.update_user_profile(db, user, req.email, req.full_name, req.phone, req.address)
    return {"status_code": 200, "message": "Cập nhật profile thành công", "data": _to_user_response(updated)}


@router.put("/profile/password")
def change_password(req: ChangePasswordRequest, db: Session = Depends(get_db),
                    user: User = Depends(get_current_user)):
    user_crud.change_password(db, user.id, req.old_password, req.new_password)
    return {"status_code": 200, "message": "Đổi mật khẩu thành công"}


# ── Admin: User Management ───────────────────────────────────────────

@router.post("/create", status_code=201)
def create_user(req: UserRequest, db: Session = Depends(get_db), _: User = Depends(require_admin)):
    u = user_crud.create_user(db, req.username, req.email, req.password or "default123",
                              req.full_name, req.phone, req.address, req.role_id)
    return {"status_code": 201, "message": "Tạo người dùng thành công", "data": _to_user_response(u)}


@router.get("")
def get_all_users(page: int = Query(0, ge=0), size: int = Query(20, ge=1, le=100),
                  search: str = Query(None), db: Session = Depends(get_db),
                  _: User = Depends(require_admin)):
    items, total = user_crud.get_all_users(db, page, size, search)
    total_pages = (total + size - 1) // size
    return {
        "status_code": 200, "message": "Lấy danh sách người dùng thành công",
        "data": {
            "content": [_to_user_response(u) for u in items], "page": page, "size": size,
            "total_elements": total, "total_pages": total_pages,
            "first": page == 0, "last": page >= total_pages - 1,
        },
    }


@router.get("/count")
def count_users(db: Session = Depends(get_db), _: User = Depends(require_admin)):
    return {"status_code": 200, "message": "OK", "data": db.query(User).count()}


@router.get("/{user_id}")
def get_user_by_id(user_id: int, db: Session = Depends(get_db), _: User = Depends(require_admin)):
    u = user_crud.get_user_by_id(db, user_id)
    return {"status_code": 200, "message": "Lấy thông tin người dùng thành công", "data": _to_user_response(u)}


@router.put("/{user_id}")
def update_user(user_id: int, req: UserRequest, db: Session = Depends(get_db),
                _: User = Depends(require_admin)):
    u = user_crud.update_user(db, user_id, full_name=req.full_name, email=req.email,
                              phone=req.phone, address=req.address, role_id=req.role_id)
    return {"status_code": 200, "message": "Cập nhật người dùng thành công", "data": _to_user_response(u)}


@router.delete("/{user_id}")
def delete_user(user_id: int, db: Session = Depends(get_db), _: User = Depends(require_admin)):
    user_crud.soft_delete_user(db, user_id)
    return {"status_code": 200, "message": "Xóa người dùng thành công"}


@router.get("/role/{role}")
def get_users_by_role(role: str, db: Session = Depends(get_db), _: User = Depends(require_admin)):
    users = user_crud.get_users_by_role(db, role)
    return {"status_code": 200, "message": "Thành công", "data": [_to_user_response(u) for u in users]}


@router.get("/check/username/{username}")
def check_username(username: str, db: Session = Depends(get_db)):
    exists = user_crud.get_user_by_username(db, username) is not None
    return {"status_code": 200, "message": "OK", "data": exists}


@router.get("/check/email/{email}")
def check_email(email: str, db: Session = Depends(get_db)):
    from app.models import User as U
    exists = db.query(U).filter(U.email == email).first() is not None
    return {"status_code": 200, "message": "OK", "data": exists}
