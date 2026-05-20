<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'quantity' => (int) $this->quantity,
            'price' => (float) $this->price,
            'unit_price' => (float) $this->price,
            'total_price' => (float) $this->price * $this->quantity,
            'primary_image_url' => $this->product?->primary_image_url,
        ];
    }
}
