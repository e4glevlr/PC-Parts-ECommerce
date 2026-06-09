.PHONY: dev backend frontend install install-backend install-frontend stop

# Chạy cả backend + frontend
dev:
	@echo "🚀 Starting backend & frontend..."
	@make backend & make frontend & wait

# Backend FastAPI
backend:
	cd backend-fastapi && uvicorn app.main:app --reload --port 8000

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
