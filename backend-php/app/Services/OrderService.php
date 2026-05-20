<?php

namespace App\Services;

use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\ResourceNotFoundException;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    private array $validStatuses = ['PENDING', 'CONFIRMED', 'PROCESSING', 'SHIPPED', 'DELIVERED', 'CANCELLED'];

    public function list(int $page, int $size, ?string $search): array
    {
        $query = Order::with(['user', 'promotion', 'orderItems.product.images'])->orderByDesc('created_at');

        if ($search) {
            $keyword = "%{$search}%";
            $query->where(function ($builder) use ($keyword) {
                $builder->where('order_code', 'ilike', $keyword)->orWhere('customer_name', 'ilike', $keyword);
            });
        }

        return $this->page($query, $page, $size);
    }

    public function byUser(int $userId, int $page, int $size, ?string $status = null): array
    {
        $query = Order::with(['user', 'promotion', 'orderItems.product.images'])
            ->where('user_id', $userId)
            ->orderByDesc('created_at');

        if ($status) {
            $query->where('status', strtoupper($status));
        }

        return $this->page($query, $page, $size);
    }

    public function byStatus(string $status, int $page, int $size): array
    {
        $query = Order::with(['user', 'promotion', 'orderItems.product.images'])
            ->where('status', strtoupper($status))
            ->orderByDesc('created_at');

        return $this->page($query, $page, $size);
    }

    public function find(int $id): Order
    {
        $order = Order::with(['user', 'promotion', 'orderItems.product.images'])->find($id);

        if (!$order) {
            throw new ResourceNotFoundException('Đơn hàng', 'id', $id);
        }

        return $order;
    }

    public function byCode(string $code): Order
    {
        $order = Order::with(['user', 'promotion', 'orderItems.product.images'])->where('order_code', $code)->first();

        if (!$order) {
            throw new ResourceNotFoundException('Đơn hàng', 'order_code', $code);
        }

        return $order;
    }

    public function createFromCart(int $userId, array $data): Order
    {
        return DB::transaction(function () use ($userId, $data) {
            $user = User::find($userId);

            if (!$user) {
                throw new ResourceNotFoundException('Người dùng', 'id', $userId);
            }

            $cart = Cart::with('cartItems.product')->where('user_id', $userId)->lockForUpdate()->first();

            if (!$cart) {
                throw new BadRequestException('Không tìm thấy giỏ hàng');
            }

            if ($cart->cartItems->isEmpty()) {
                throw new BadRequestException('Giỏ hàng đang trống');
            }

            $subtotal = $cart->cartItems->sum(fn ($item) => (float) $item->product->price * $item->quantity);
            $tax = round($subtotal * (float) config('shop.vat_rate'));
            $shipping = $subtotal >= (float) config('shop.shipping_threshold') ? 0 : (float) config('shop.shipping_fee');
            $gross = $subtotal + $tax + $shipping;
            $discount = 0;
            $promotionId = null;

            if (!empty($data['promotion_id'])) {
                $promotion = Promotion::find($data['promotion_id']);

                if ($promotion && $this->isPromotionCurrentlyActive($promotion) && $subtotal >= (float) $promotion->minimum_order_amount) {
                    $discount = $this->calculateDiscount($promotion, $subtotal);
                    $promotionId = $promotion->id;
                }
            }

            $order = Order::create([
                'order_code' => 'ORD-'.Carbon::now('UTC')->format('YmdHis').'-'.strtoupper(Str::random(6)),
                'user_id' => $userId,
                'customer_name' => $data['customer_name'] ?? $user->full_name,
                'customer_email' => $data['customer_email'] ?? $user->email,
                'total_amount' => $subtotal,
                'discount_amount' => $discount,
                'final_amount' => max($gross - $discount, 0),
                'promotion_id' => $promotionId,
                'status' => 'PENDING',
                'payment_method' => $data['payment_method'] ?? 'COD',
                'shipping_address' => $data['shipping_address'],
                'shipping_phone' => $data['shipping_phone'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($cart->cartItems as $item) {
                $product = Product::where('id', $item->product_id)->lockForUpdate()->first();

                if (!$product->is_active) {
                    throw new BadRequestException("Sản phẩm không khả dụng: {$product->name}");
                }

                if ($product->quantity < $item->quantity) {
                    throw new BadRequestException("Không đủ hàng: {$product->name}");
                }

                $product->decrement('quantity', $item->quantity);

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $item->quantity,
                    'price' => $product->price,
                    'created_at' => Carbon::now(),
                ]);
            }

            $cart->cartItems()->delete();
            $cart->touch();

            return $order->refresh()->load(['user', 'promotion', 'orderItems.product.images']);
        });
    }

    public function updateStatus(int $orderId, string $status): Order
    {
        $normalized = strtoupper($status);

        if (!in_array($normalized, $this->validStatuses, true)) {
            throw new BadRequestException("Trạng thái không hợp lệ: {$status}");
        }

        $order = $this->find($orderId);
        $order->update(['status' => $normalized]);

        return $order->refresh()->load(['user', 'promotion', 'orderItems.product.images']);
    }

    public function cancel(int $orderId): Order
    {
        return DB::transaction(function () use ($orderId) {
            $order = $this->find($orderId);

            if ($order->status === 'DELIVERED') {
                throw new BadRequestException('Không thể hủy đơn đã giao');
            }

            if ($order->status === 'CANCELLED') {
                throw new BadRequestException('Đơn hàng đã bị hủy trước đó');
            }

            foreach ($order->orderItems as $item) {
                Product::where('id', $item->product_id)->increment('quantity', $item->quantity);
            }

            $order->update(['status' => 'CANCELLED']);

            return $order->refresh()->load(['user', 'promotion', 'orderItems.product.images']);
        });
    }

    public function assertVisibleTo(User $user, Order $order): void
    {
        if (!in_array($user->role->name, ['ADMIN', 'STAFF'], true) && $order->user_id !== $user->id) {
            throw new ForbiddenException();
        }
    }

    public function stats(): array
    {
        return [
            'total_orders' => Order::count(),
            'pending_orders' => Order::where('status', 'PENDING')->count(),
            'processing_orders' => Order::where('status', 'PROCESSING')->count(),
            'shipped_orders' => Order::where('status', 'SHIPPED')->count(),
            'delivered_orders' => Order::where('status', 'DELIVERED')->count(),
            'cancelled_orders' => Order::where('status', 'CANCELLED')->count(),
            'total_revenue' => (float) Order::where('status', 'DELIVERED')->sum('final_amount'),
        ];
    }

    public function calculateDiscount(Promotion $promotion, float $originalPrice): float
    {
        if ($promotion->discount_type === 'PERCENTAGE') {
            return $originalPrice * (float) $promotion->discount_value / 100;
        }

        return min((float) $promotion->discount_value, $originalPrice);
    }

    public function isPromotionCurrentlyActive(Promotion $promotion): bool
    {
        $now = Carbon::now();

        return $promotion->is_active && $promotion->start_date <= $now && $promotion->end_date >= $now;
    }

    private function page($query, int $page, int $size): array
    {
        $total = $query->count();
        $items = $query->offset($page * $size)->limit($size)->get();

        return [$items, $total];
    }
}
