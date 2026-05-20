<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $product = $this->product;
        $price = $product ? (float) $product->price : 0;

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $product?->name ?? '?',
            'product_price' => $price,
            'product_image_url' => $product?->primary_image_url,
            'primary_image_url' => $product?->primary_image_url,
            'is_product_active' => (bool) ($product?->is_active ?? false),
            'quantity' => (int) $this->quantity,
            'sub_total' => $price * $this->quantity,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
