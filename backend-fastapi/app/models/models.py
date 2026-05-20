from datetime import datetime

from sqlalchemy import BigInteger, String, Boolean, Text, ForeignKey, Numeric, Integer, DateTime, JSON, UniqueConstraint, Sequence
from sqlalchemy.orm import Mapped, mapped_column, relationship

from app.db.session import Base


# ── Role ───────────────────────────────────────────────────────────────

class Role(Base):
    __tablename__ = "roles"

    id: Mapped[int] = mapped_column(BigInteger, Sequence("roles_seq"), primary_key=True)
    name: Mapped[str] = mapped_column(String(50), unique=True, nullable=False)

    users: Mapped[list["User"]] = relationship(back_populates="role", lazy="select")


# ── User ───────────────────────────────────────────────────────────────

class User(Base):
    __tablename__ = "users"

    id: Mapped[int] = mapped_column(BigInteger, Sequence("users_seq"), primary_key=True)
    username: Mapped[str] = mapped_column(String(50), unique=True, nullable=False)
    email: Mapped[str] = mapped_column(String(100), unique=True, nullable=False)
    password: Mapped[str] = mapped_column(String(255), nullable=False)
    full_name: Mapped[str] = mapped_column(String(100), nullable=False)
    phone: Mapped[str] = mapped_column(String(20), unique=True, nullable=False)
    address: Mapped[str | None] = mapped_column(Text)
    role_id: Mapped[int] = mapped_column(BigInteger, ForeignKey("roles.id"), nullable=False)
    is_active: Mapped[bool] = mapped_column(Boolean, default=True)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=datetime.utcnow)
    updated_at: Mapped[datetime] = mapped_column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)

    role: Mapped["Role"] = relationship(back_populates="users", lazy="joined")
    tokens: Mapped[list["Token"]] = relationship(back_populates="user", lazy="select")
    cart: Mapped["Cart | None"] = relationship(back_populates="user", uselist=False, lazy="select")


# ── Token ──────────────────────────────────────────────────────────────

class Token(Base):
    __tablename__ = "tokens"

    id: Mapped[int] = mapped_column(BigInteger, Sequence("tokens_seq"), primary_key=True)
    token: Mapped[str] = mapped_column(String(500), unique=True, nullable=False)
    token_type: Mapped[str] = mapped_column(String(50), nullable=False, default="ACCESS_TOKEN")
    expiration_date: Mapped[datetime] = mapped_column(DateTime, nullable=False)
    revoked: Mapped[bool] = mapped_column(Boolean, default=False, nullable=False)
    expired: Mapped[bool] = mapped_column(Boolean, default=False, nullable=False)
    user_id: Mapped[int] = mapped_column(BigInteger, ForeignKey("users.id", ondelete="CASCADE"), nullable=False)

    user: Mapped["User"] = relationship(back_populates="tokens", lazy="joined")


# ── Category ───────────────────────────────────────────────────────────

class Category(Base):
    __tablename__ = "categories"

    id: Mapped[int] = mapped_column(BigInteger, Sequence("categories_seq"), primary_key=True)
    name: Mapped[str] = mapped_column(String(100), nullable=False)
    description: Mapped[str | None] = mapped_column(Text)
    parent_category_id: Mapped[int | None] = mapped_column(BigInteger, ForeignKey("categories.id", ondelete="SET NULL"))
    is_active: Mapped[bool] = mapped_column(Boolean, default=True)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=datetime.utcnow)
    updated_at: Mapped[datetime] = mapped_column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)

    parent_category: Mapped["Category | None"] = relationship(remote_side="Category.id", lazy="joined")
    products: Mapped[list["Product"]] = relationship(back_populates="category", lazy="select")
    attribute_definitions: Mapped[list["AttributeDefinition"]] = relationship(back_populates="category", lazy="select")


# ── Product ────────────────────────────────────────────────────────────

class Product(Base):
    __tablename__ = "products"

    id: Mapped[int] = mapped_column(BigInteger, Sequence("products_seq"), primary_key=True)
    name: Mapped[str] = mapped_column(String(200), nullable=False)
    description: Mapped[str | None] = mapped_column(Text)
    price: Mapped[float] = mapped_column(Numeric(15, 2), nullable=False)
    quantity: Mapped[int] = mapped_column(Integer, nullable=False, default=0)
    low_stock_threshold: Mapped[int] = mapped_column(Integer, default=10)
    category_id: Mapped[int] = mapped_column(BigInteger, ForeignKey("categories.id"), nullable=False)
    specifications: Mapped[dict | None] = mapped_column(JSON)
    attributes: Mapped[dict | None] = mapped_column(JSON, default=dict)
    is_active: Mapped[bool] = mapped_column(Boolean, default=True)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=datetime.utcnow)
    updated_at: Mapped[datetime] = mapped_column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)

    category: Mapped["Category"] = relationship(back_populates="products", lazy="joined")
    images: Mapped[list["ProductImage"]] = relationship(back_populates="product", lazy="joined", cascade="all, delete-orphan")
    comments: Mapped[list["Comment"]] = relationship(back_populates="product", lazy="select")
    inventory_logs: Mapped[list["InventoryLog"]] = relationship(back_populates="product", lazy="select")

    @property
    def is_low_stock(self) -> bool:
        return self.quantity <= self.low_stock_threshold

    @property
    def primary_image_url(self) -> str:
        if self.images:
            for img in self.images:
                if img.is_primary:
                    return img.image_url
            return self.images[0].image_url
        return "https://cdn.image.com/example.jpg"


# ── ProductImage ───────────────────────────────────────────────────────

class ProductImage(Base):
    __tablename__ = "product_images"

    id: Mapped[int] = mapped_column(BigInteger, Sequence("product_images_seq"), primary_key=True)
    product_id: Mapped[int] = mapped_column(BigInteger, ForeignKey("products.id", ondelete="CASCADE"), nullable=False)
    image_url: Mapped[str] = mapped_column(String(500), nullable=False)
    is_primary: Mapped[bool] = mapped_column(Boolean, default=False)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=datetime.utcnow)

    product: Mapped["Product"] = relationship(back_populates="images", lazy="select")


# ── AttributeDefinition ───────────────────────────────────────────────

class AttributeDefinition(Base):
    __tablename__ = "attribute_definitions"
    __table_args__ = (UniqueConstraint("category_id", "code", name="uq_attrdef_category_code"),)

    id: Mapped[int] = mapped_column(BigInteger, Sequence("attributes_seq"), primary_key=True)
    category_id: Mapped[int] = mapped_column(BigInteger, ForeignKey("categories.id", ondelete="CASCADE"), nullable=False)
    code: Mapped[str] = mapped_column(String(100), nullable=False)
    display_name: Mapped[str] = mapped_column(String(200), nullable=False)
    data_type: Mapped[str] = mapped_column(String(20), nullable=False)
    input_type: Mapped[str] = mapped_column(String(30), nullable=False)
    unit: Mapped[str | None] = mapped_column(String(50))
    sort_order: Mapped[int | None] = mapped_column(Integer)
    options: Mapped[dict | None] = mapped_column(JSON)
    is_active: Mapped[bool] = mapped_column(Boolean, default=True)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=datetime.utcnow)
    updated_at: Mapped[datetime] = mapped_column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)

    category: Mapped["Category"] = relationship(back_populates="attribute_definitions", lazy="joined")


# ── Cart ───────────────────────────────────────────────────────────────

class Cart(Base):
    __tablename__ = "carts"

    id: Mapped[int] = mapped_column(BigInteger, Sequence("carts_seq"), primary_key=True)
    user_id: Mapped[int] = mapped_column(BigInteger, ForeignKey("users.id", ondelete="CASCADE"), unique=True, nullable=False)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=datetime.utcnow)
    updated_at: Mapped[datetime] = mapped_column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)

    user: Mapped["User"] = relationship(back_populates="cart", lazy="joined")
    cart_items: Mapped[list["CartItem"]] = relationship(back_populates="cart", lazy="joined", cascade="all, delete-orphan")


# ── CartItem ──────────────────────────────────────────────────────────

class CartItem(Base):
    __tablename__ = "cart_items"
    __table_args__ = (UniqueConstraint("cart_id", "product_id"),)

    id: Mapped[int] = mapped_column(BigInteger, Sequence("cart_items_seq"), primary_key=True)
    cart_id: Mapped[int] = mapped_column(BigInteger, ForeignKey("carts.id", ondelete="CASCADE"), nullable=False)
    product_id: Mapped[int] = mapped_column(BigInteger, ForeignKey("products.id", ondelete="CASCADE"), nullable=False)
    quantity: Mapped[int] = mapped_column(Integer, nullable=False, default=1)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=datetime.utcnow)
    updated_at: Mapped[datetime] = mapped_column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)

    cart: Mapped["Cart"] = relationship(back_populates="cart_items", lazy="select")
    product: Mapped["Product"] = relationship(lazy="joined")


# ── Order ──────────────────────────────────────────────────────────────

class Order(Base):
    __tablename__ = "orders"

    id: Mapped[int] = mapped_column(BigInteger, Sequence("orders_seq"), primary_key=True)
    order_code: Mapped[str] = mapped_column(String(50), unique=True, nullable=False)
    user_id: Mapped[int] = mapped_column(BigInteger, ForeignKey("users.id"), nullable=False)
    customer_name: Mapped[str] = mapped_column(String(100), nullable=False)
    customer_email: Mapped[str] = mapped_column(String(100), nullable=False)
    total_amount: Mapped[float] = mapped_column(Numeric(15, 2), nullable=False)
    discount_amount: Mapped[float] = mapped_column(Numeric(15, 2), default=0)
    final_amount: Mapped[float] = mapped_column(Numeric(15, 2), nullable=False)
    promotion_id: Mapped[int | None] = mapped_column(BigInteger, ForeignKey("promotions.id", ondelete="SET NULL"))
    status: Mapped[str] = mapped_column(String(20), nullable=False, default="PENDING")
    payment_method: Mapped[str] = mapped_column(String(20), default="COD")
    shipping_address: Mapped[str] = mapped_column(Text, nullable=False)
    shipping_phone: Mapped[str | None] = mapped_column(String(20))
    notes: Mapped[str | None] = mapped_column(Text)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=datetime.utcnow)
    updated_at: Mapped[datetime] = mapped_column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)

    user: Mapped["User"] = relationship(lazy="joined")
    promotion: Mapped["Promotion | None"] = relationship(lazy="joined")
    order_items: Mapped[list["OrderItem"]] = relationship(back_populates="order", lazy="joined", cascade="all, delete-orphan")


# ── OrderItem ─────────────────────────────────────────────────────────

class OrderItem(Base):
    __tablename__ = "order_items"

    id: Mapped[int] = mapped_column(BigInteger, Sequence("order_items_seq"), primary_key=True)
    order_id: Mapped[int] = mapped_column(BigInteger, ForeignKey("orders.id", ondelete="CASCADE"), nullable=False)
    product_id: Mapped[int] = mapped_column(BigInteger, ForeignKey("products.id"), nullable=False)
    product_name: Mapped[str] = mapped_column(String(200), nullable=False)
    quantity: Mapped[int] = mapped_column(Integer, nullable=False)
    price: Mapped[float] = mapped_column(Numeric(15, 2), nullable=False)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=datetime.utcnow)

    order: Mapped["Order"] = relationship(back_populates="order_items", lazy="select")
    product: Mapped["Product"] = relationship(lazy="joined")


# ── Comment ────────────────────────────────────────────────────────────

class Comment(Base):
    __tablename__ = "comments"

    id: Mapped[int] = mapped_column(BigInteger, Sequence("comments_seq"), primary_key=True)
    user_id: Mapped[int] = mapped_column(BigInteger, ForeignKey("users.id", ondelete="CASCADE"), nullable=False)
    product_id: Mapped[int] = mapped_column(BigInteger, ForeignKey("products.id", ondelete="CASCADE"), nullable=False)
    parent_comment_id: Mapped[int | None] = mapped_column(BigInteger, ForeignKey("comments.id", ondelete="CASCADE"))
    content: Mapped[str] = mapped_column(Text, nullable=False)
    is_staff_reply: Mapped[bool] = mapped_column(Boolean, default=False)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=datetime.utcnow)
    updated_at: Mapped[datetime] = mapped_column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)

    user: Mapped["User"] = relationship(lazy="joined")
    product: Mapped["Product"] = relationship(back_populates="comments", lazy="select")
    parent_comment: Mapped["Comment | None"] = relationship(remote_side="Comment.id", lazy="select")
    replies: Mapped[list["Comment"]] = relationship(lazy="select")


# ── InventoryLog ──────────────────────────────────────────────────────

class InventoryLog(Base):
    __tablename__ = "inventory_logs"

    id: Mapped[int] = mapped_column(BigInteger, Sequence("inventory_logs_seq"), primary_key=True)
    product_id: Mapped[int] = mapped_column(BigInteger, ForeignKey("products.id"), nullable=False)
    change_type: Mapped[str] = mapped_column(String(10), nullable=False)  # IN | OUT
    quantity_change: Mapped[int] = mapped_column(Integer, nullable=False)
    reason: Mapped[str | None] = mapped_column(String(200))
    performed_by: Mapped[int] = mapped_column(BigInteger, ForeignKey("users.id"), nullable=False)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=datetime.utcnow)

    product: Mapped["Product"] = relationship(back_populates="inventory_logs", lazy="joined")
    performer: Mapped["User"] = relationship(lazy="joined", foreign_keys=[performed_by])


# ── Promotion ─────────────────────────────────────────────────────────

class Promotion(Base):
    __tablename__ = "promotions"

    id: Mapped[int] = mapped_column(BigInteger, Sequence("promotions_seq"), primary_key=True)
    name: Mapped[str] = mapped_column(String(200), nullable=False)
    description: Mapped[str | None] = mapped_column(Text)
    discount_type: Mapped[str] = mapped_column(String(20), nullable=False)  # PERCENTAGE | FIXED_AMOUNT
    discount_value: Mapped[float] = mapped_column(Numeric(15, 2), nullable=False)
    minimum_order_amount: Mapped[float] = mapped_column(Numeric(15, 2), default=0)
    start_date: Mapped[datetime] = mapped_column(DateTime, nullable=False)
    end_date: Mapped[datetime] = mapped_column(DateTime, nullable=False)
    is_active: Mapped[bool] = mapped_column(Boolean, default=True)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=datetime.utcnow)
    updated_at: Mapped[datetime] = mapped_column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)
