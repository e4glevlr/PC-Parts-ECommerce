<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug ?? null,
            'description' => $this->description,
            'parent_category_id' => $this->parent_category_id,
            'parent_category_name' => $this->relationLoaded('parentCategory') ? $this->parentCategory?->name : null,
            'is_active' => (bool) $this->is_active,
            'children' => $this->relationLoaded('children') ? CategoryResource::collection($this->children)->resolve($request) : [],
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
