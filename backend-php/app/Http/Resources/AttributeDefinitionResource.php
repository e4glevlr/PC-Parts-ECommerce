<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttributeDefinitionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'code' => $this->code,
            'display_name' => $this->display_name,
            'data_type' => $this->data_type,
            'input_type' => $this->input_type,
            'unit' => $this->unit,
            'sort_order' => $this->sort_order,
            'options' => $this->options,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
