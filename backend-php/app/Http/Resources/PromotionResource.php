<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromotionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $now = Carbon::now();
        $notStarted = $now->lt($this->start_date);
        $expired = $now->gt($this->end_date);
        $currentlyActive = $this->is_active && !$notStarted && !$expired;
        $status = 'ACTIVE';

        if (!$this->is_active) {
            $status = 'INACTIVE';
        } elseif ($notStarted) {
            $status = 'NOT_STARTED';
        } elseif ($expired) {
            $status = 'EXPIRED';
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'discount_type' => $this->discount_type,
            'discountType' => $this->discount_type,
            'discount_value' => (float) $this->discount_value,
            'discountValue' => (float) $this->discount_value,
            'minimum_order_amount' => (float) $this->minimum_order_amount,
            'minimumOrderAmount' => (float) $this->minimum_order_amount,
            'min_order_value' => (float) $this->minimum_order_amount,
            'start_date' => $this->start_date?->toDateTimeString(),
            'startDate' => $this->start_date?->toDateTimeString(),
            'end_date' => $this->end_date?->toDateTimeString(),
            'endDate' => $this->end_date?->toDateTimeString(),
            'is_active' => (bool) $this->is_active,
            'isActive' => (bool) $this->is_active,
            'is_currently_active' => $currentlyActive,
            'is_expired' => $expired,
            'is_not_started' => $notStarted,
            'status' => $status,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
