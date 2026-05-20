<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $items = $this->cartItems;
        $totalPrice = $items->sum(fn ($item) => (float) ($item->product?->price ?? 0) * $item->quantity);
        $itemCount = $items->sum('quantity');

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'items' => CartItemResource::collection($items)->resolve($request),
            'cart_items' => CartItemResource::collection($items)->resolve($request),
            'total_items' => $itemCount,
            'total_price' => $totalPrice,
            'total_amount' => $totalPrice,
            'subtotal' => $totalPrice,
            'tax_amount' => 0,
            'shipping_cost' => 0,
            'discount_amount' => 0,
            'final_amount' => $totalPrice,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
