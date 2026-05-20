<?php

namespace App\Services;

use App\Exceptions\ResourceNotFoundException;
use App\Models\Promotion;
use Carbon\Carbon;

class PromotionService
{
    public function list(int $page, int $size, ?string $status = null, ?string $discountType = null, ?string $search = null, ?bool $isActive = null): array
    {
        $query = Promotion::query()->orderByDesc('created_at');

        if ($discountType) {
            $query->where('discount_type', $discountType);
        }

        if ($search) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        if ($isActive !== null) {
            $query->where('is_active', $isActive);
        }

        if ($status === 'ACTIVE') {
            $now = Carbon::now();
            $query->where('is_active', true)->where('start_date', '<=', $now)->where('end_date', '>=', $now);
        } elseif ($status === 'INACTIVE') {
            $query->where('is_active', false);
        }

        $total = $query->count();
        $items = $query->offset($page * $size)->limit($size)->get();

        return [$items, $total];
    }

    public function active()
    {
        $now = Carbon::now();

        return Promotion::where('is_active', true)->where('start_date', '<=', $now)->where('end_date', '>=', $now)->get();
    }

    public function find(int $id): Promotion
    {
        $promotion = Promotion::find($id);

        if (!$promotion) {
            throw new ResourceNotFoundException('Khuyến mãi', 'id', $id);
        }

        return $promotion;
    }

    public function create(array $data): Promotion
    {
        return Promotion::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'discount_type' => $data['discount_type'],
            'discount_value' => $data['discount_value'],
            'minimum_order_amount' => $data['minimum_order_amount'] ?? 0,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function update(int $id, array $data): Promotion
    {
        $promotion = $this->find($id);
        $promotion->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'discount_type' => $data['discount_type'],
            'discount_value' => $data['discount_value'],
            'minimum_order_amount' => $data['minimum_order_amount'] ?? 0,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        return $promotion->refresh();
    }

    public function delete(int $id): void
    {
        $this->find($id)->update(['is_active' => false]);
    }

    public function activate(int $id): Promotion
    {
        $promotion = $this->find($id);
        $promotion->update(['is_active' => true]);

        return $promotion->refresh();
    }

    public function deactivate(int $id): Promotion
    {
        $promotion = $this->find($id);
        $promotion->update(['is_active' => false]);

        return $promotion->refresh();
    }

    public function applicable(float $price)
    {
        return $this->active()->filter(fn ($promotion) => $price >= (float) $promotion->minimum_order_amount)->values();
    }

    public function best(float $price): ?Promotion
    {
        return $this->applicable($price)->sortByDesc(fn ($promotion) => $this->calculateDiscount($promotion, $price))->first();
    }

    public function calculateDiscount(Promotion $promotion, float $originalPrice): float
    {
        if (!$this->isCurrent($promotion) || $originalPrice < (float) $promotion->minimum_order_amount) {
            return 0;
        }

        if ($promotion->discount_type === 'PERCENTAGE') {
            return $originalPrice * (float) $promotion->discount_value / 100;
        }

        return min((float) $promotion->discount_value, $originalPrice);
    }

    private function isCurrent(Promotion $promotion): bool
    {
        $now = Carbon::now();

        return $promotion->is_active && $promotion->start_date <= $now && $promotion->end_date >= $now;
    }
}
