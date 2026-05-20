<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product?->name,
            'change_type' => $this->change_type,
            'quantity_change' => (int) $this->quantity_change,
            'reason' => $this->reason,
            'performed_by' => $this->performed_by,
            'performer_name' => $this->performer?->full_name,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
