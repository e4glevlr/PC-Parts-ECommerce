.PHONY: dev backend backend-php frontend install install-backend install-frontend stop win

# Chạy cả backend + frontend
dev:
	@echo "🚀 Starting backend & frontend..."
	@make backend & make frontend & wait

# Backend FastAPI
backend:
	cd backend-fastapi && uvicorn app.main:app --reload --port 8000

# Backend PHP (Laravel) - DB chạy trên VPS
backend-php:
	cd backend-php && php artisan serve --port=8080

# Windows: setup + chạy PHP backend + frontend (1 lệnh xong hết)
win:
	cmd /c run-windows.bat

# Frontend Vite
frontend:
	cd frontend && npm run dev

# Cài dependencies
install: install-backend install-frontend

install-backend:
	cd backend-fastapi && pip install -r requirements.txt

install-frontend:
	cd frontend && npm install

# Dừng tất cả
stop:
	@pkill -f "uvicorn" 2>/dev/null || true
	@pkill -f "vite" 2>/dev/null || true
	@echo "⏹ Stopped all services"
