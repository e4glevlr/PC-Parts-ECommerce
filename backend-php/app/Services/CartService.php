<?php

namespace App\Services;

use App\Exceptions\BadRequestException;
use App\Exceptions\ResourceNotFoundException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;

class CartService
{
    public function getOrCreate(int $userId): Cart
    {
        if (!User::where('id', $userId)->exists()) {
            throw new ResourceNotFoundException('Người dùng', 'id', $userId);
        }

        return Cart::firstOrCreate(['user_id' => $userId])->load(['cartItems.product.images']);
    }

    public function addItem(int $userId, int $productId, int $quantity): Cart
    {
        $cart = $this->getOrCreate($userId);
        $product = Product::find($productId);

        if (!$product) {
            throw new ResourceNotFoundException('Sản phẩm', 'id', $productId);
        }

        if (!$product->is_active) {
            throw new BadRequestException('Sản phẩm hiện không hoạt động');
        }

        if ($product->quantity < $quantity) {
            throw new BadRequestException("Không đủ hàng: {$product->name}");
        }

        $item = CartItem::where('cart_id', $cart->id)->where('product_id', $productId)->first();

        if ($item) {
            $newQuantity = $item->quantity + $quantity;

            if ($product->quantity < $newQuantity) {
                throw new BadRequestException("Không đủ hàng: {$product->name}");
            }

            $item->update(['quantity' => $newQuantity]);
        } else {
            CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $productId,
                'quantity' => $quantity,
            ]);
        }

        $cart->touch();

        return $cart->refresh()->load(['cartItems.product.images']);
    }

    public function updateItem(int $userId, int $itemId, int $quantity): Cart
    {
        $cart = $this->getOrCreate($userId);
        $item = CartItem::with('product')->find($itemId);

        if (!$item) {
            throw new ResourceNotFoundException('Mục giỏ hàng', 'id', $itemId);
        }

        if ($item->cart_id !== $cart->id) {
            throw new BadRequestException('Mục giỏ hàng không thuộc người dùng này');
        }

        if ($quantity <= 0) {
            throw new BadRequestException('Số lượng phải lớn hơn 0');
        }

        if ($item->product->quantity < $quantity) {
            throw new BadRequestException("Không đủ hàng: {$item->product->name}");
        }

        $item->update(['quantity' => $quantity]);
        $cart->touch();

        return $cart->refresh()->load(['cartItems.product.images']);
    }

    public function removeItem(int $userId, int $itemId): Cart
    {
        $cart = $this->getOrCreate($userId);
        $item = CartItem::find($itemId);

        if (!$item) {
            throw new ResourceNotFoundException('Mục giỏ hàng', 'id', $itemId);
        }

        if ($item->cart_id !== $cart->id) {
            throw new BadRequestException('Mục giỏ hàng không thuộc người dùng này');
        }

        $item->delete();
        $cart->touch();

        return $cart->refresh()->load(['cartItems.product.images']);
    }

    public function clear(int $userId): void
    {
        $cart = Cart::where('user_id', $userId)->first();

        if (!$cart) {
            return;
        }

        CartItem::where('cart_id', $cart->id)->delete();
        $cart->touch();
    }

    public function merge(int $userId, array $guestItems): Cart
    {
        $cart = $this->getOrCreate($userId);

        foreach ($guestItems as $guestItem) {
            $product = Product::find($guestItem['product_id']);

            if (!$product || !$product->is_active) {
                continue;
            }

            $item = CartItem::where('cart_id', $cart->id)->where('product_id', $guestItem['product_id'])->first();

            if ($item) {
                $newQuantity = $item->quantity + $guestItem['quantity'];

                if ($product->quantity >= $newQuantity) {
                    $item->update(['quantity' => $newQuantity]);
                }
            } elseif ($product->quantity >= $guestItem['quantity']) {
                CartItem::create([
                    'cart_id' => $cart->id,
                    'product_id' => $guestItem['product_id'],
                    'quantity' => $guestItem['quantity'],
                ]);
            }
        }

        $cart->touch();

        return $cart->refresh()->load(['cartItems.product.images']);
    }
}
