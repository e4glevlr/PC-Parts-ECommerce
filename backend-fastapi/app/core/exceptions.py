from fastapi import HTTPException, status


class ResourceNotFoundException(HTTPException):
    def __init__(self, resource: str = "Dữ liệu", field: str = "id", value=None):
        # Thông báo thân thiện, không lộ tên trường kỹ thuật (vd "id") cho người dùng cuối
        detail = f"Không tìm thấy {resource.lower()}"
        super().__init__(status_code=status.HTTP_404_NOT_FOUND, detail=detail)


class BadRequestException(HTTPException):
    def __init__(self, detail: str = "Yêu cầu không hợp lệ"):
        super().__init__(status_code=status.HTTP_400_BAD_REQUEST, detail=detail)


class UnauthorizedException(HTTPException):
    def __init__(self, detail: str = "Yêu cầu xác thực"):
        super().__init__(status_code=status.HTTP_401_UNAUTHORIZED, detail=detail)


class ForbiddenException(HTTPException):
    def __init__(self, detail: str = "Không có quyền truy cập"):
        super().__init__(status_code=status.HTTP_403_FORBIDDEN, detail=detail)
