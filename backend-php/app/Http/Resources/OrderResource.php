<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_code' => $this->order_code,
            'user_id' => $this->user_id,
            'user_name' => $this->relationLoaded('user') ? $this->user?->full_name : null,
            'user_username' => $this->relationLoaded('user') ? $this->user?->username : null,
            'user_email' => $this->relationLoaded('user') ? $this->user?->email : null,
            'user_phone' => $this->relationLoaded('user') ? $this->user?->phone : null,
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'total_amount' => (float) $this->total_amount,
            'discount_amount' => (float) $this->discount_amount,
            'final_amount' => (float) $this->final_amount,
            'promotion_id' => $this->promotion_id,
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'shipping_address' => $this->shipping_address,
            'shipping_phone' => $this->shipping_phone,
            'notes' => $this->notes,
            'order_items' => $this->relationLoaded('orderItems') ? OrderItemResource::collection($this->orderItems)->resolve($request) : [],
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
