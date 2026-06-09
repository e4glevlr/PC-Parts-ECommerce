# PC Parts E-Commerce

A full-stack e-commerce website for PC components built with **FastAPI** (backend) and **React/Vite** (frontend).

## Quickstart

1. **Set up your PostgreSQL database.**
2. **Run the FastAPI backend (Python 3.10+ / uvicorn).**
3. **Start the frontend React/Vite (Node 18+).**

The fastest path is the `Makefile`:

```bash
make install   # install backend + frontend deps
make dev       # run backend (:8000) and frontend (:5173) together
```

## Requirements

- **PostgreSQL** database server
- **Python 3.10+** and **pip** for the backend
- **Node.js 18+** and **npm** (or pnpm/yarn) for the frontend

## 1. Database Setup

Create a PostgreSQL database and configure your connection string.

```bash
psql -h localhost -p 5432 -U your_username -d pc_shop_database
```

Apply the schema and (optionally) seed data from the `sql/` directory:

```bash
psql -d pc_shop_database -f sql/database_schema.sql
psql -d pc_shop_database -f sql/test_products.sql   # optional sample data
psql -d pc_shop_database -f sql/test_images.sql     # optional sample data
```

## 2. Backend (FastAPI)

```bash
cd backend-fastapi
cp .env.example .env            # then edit DATABASE_URL / JWT_SECRET
pip install -r requirements.txt
uvicorn app.main:app --reload --port 8000
```

- Backend runs at `http://localhost:8000` by default.
- Interactive API docs: `http://localhost:8000/swagger-ui` (or `/redoc`).
- API is served under the `/api/v1` prefix.

### Configuration

Configure the backend via `backend-fastapi/.env` (see `backend-fastapi/.env.example`):

| Variable | Description | Default |
|---|---|---|
| `DATABASE_URL` | PostgreSQL connection string | `postgresql://user:pass@localhost:5432/pc_shop_database` |
| `JWT_SECRET` | Secret used to sign JWTs | change in production |
| `JWT_EXPIRATION_SECONDS` | Access token lifetime | `2592000` (30 days) |
| `CORS_ALLOWED_ORIGINS` | Comma-separated origins or `*` | `*` |
| `FILE_STORAGE_LOCATION` | Upload directory | `images` |
| `SERVER_PORT` | Port uvicorn listens on | `8000` |

## 3. Frontend

```bash
cd frontend
npm install --legacy-peer-deps
npm run dev
```

- Frontend runs at `http://localhost:5173` by default.
- Configure the backend URL via `VITE_API_BASE_URL` (default `http://localhost:8000/api/v1`).

## 4. Test Accounts

After seeding the database, you can use these accounts:

```
ROLE ADMIN
username: admin
password: (set in your database)

ROLE STAFF
username: staff
password: (set in your database)

ROLE CUSTOMER
username: customer
password: (set in your database)
```

## 5. Docker Compose Deployment

Use Docker to build and run frontend + backend together.

### Container Structure

- **backend**: FastAPI (Python 3.11) served by uvicorn on port 8000.
- **frontend**: React/Vite built and served by Nginx.
- **docker-compose.yml** combines both services.

### Environment Variables

Create a `.env` file at the project root (see `.env.example`):

```bash
DATABASE_URL=postgresql://your_username:your_password@your-db-host:5432/your_database
JWT_SECRET=your_jwt_secret_key
CORS_ALLOWED_ORIGINS=*
VITE_API_BASE_URL=http://localhost:8000/api/v1
VITE_GEMINI_API_KEY=your_gemini_api_key  # Optional: for AI advisor feature
```

### Build & Run

```bash
# From project root
docker compose up -d --build

# View logs
docker compose logs -f backend
docker compose logs -f frontend
```

## Project Structure

```
├── backend-fastapi/    # FastAPI Python backend
├── frontend/           # React/Vite TypeScript frontend
├── pc-part-dataset/    # Sample product data (CSV/JSON)
├── scripts/            # Utility scripts (data import, SQL generators)
├── sql/                # Database schema + seed/test data
├── docs/               # Architecture & data-flow diagrams
├── docker-compose.yml  # Docker orchestration
└── Makefile            # Dev/run shortcuts
```

## Tech Stack

### Backend

- Python 3.10+
- FastAPI + uvicorn
- SQLAlchemy 2 + Alembic
- PostgreSQL (psycopg2)
- JWT auth (python-jose) + passlib/bcrypt

### Frontend

- React 18
- TypeScript
- Vite
- Material UI
- Redux Toolkit

## License

MIT License
