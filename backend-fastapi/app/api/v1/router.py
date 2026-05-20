from fastapi import APIRouter

from app.api.v1.endpoints import users, products, orders, cart, categories, comments, inventory, promotions

api_router = APIRouter()

api_router.include_router(users.router, prefix="/users", tags=["Users"])
api_router.include_router(products.router, prefix="/products", tags=["Products"])
api_router.include_router(orders.router, prefix="/orders", tags=["Orders"])
api_router.include_router(cart.router, prefix="/cart", tags=["Cart"])
api_router.include_router(categories.router, prefix="/categories", tags=["Categories"])
api_router.include_router(comments.router, prefix="/comments", tags=["Comments"])
api_router.include_router(inventory.router, prefix="/inventory", tags=["Inventory"])
api_router.include_router(promotions.router, prefix="/promotions", tags=["Promotions"])
