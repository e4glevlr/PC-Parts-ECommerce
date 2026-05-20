<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'quantity' => (int) $this->quantity,
            'low_stock_threshold' => (int) $this->low_stock_threshold,
            'category_id' => $this->category_id,
            'category_name' => $this->relationLoaded('category') ? $this->category?->name : null,
            'category' => $this->relationLoaded('category') && $this->category ? (new CategoryResource($this->category))->toArray($request) : null,
            'specifications' => $this->specifications,
            'attributes' => $this->attributes,
            'images' => $this->relationLoaded('images') ? ProductImageResource::collection($this->images)->resolve($request) : [],
            'primary_image_url' => $this->primary_image_url,
            'image_url' => $this->primary_image_url,
            'is_active' => (bool) $this->is_active,
            'is_low_stock' => (bool) $this->is_low_stock,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
