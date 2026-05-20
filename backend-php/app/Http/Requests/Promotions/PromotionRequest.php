<?php

namespace App\Http\Requests\Promotions;

use Illuminate\Foundation\Http\FormRequest;

class PromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'discount_type' => ['required', 'in:PERCENTAGE,FIXED_AMOUNT'],
            'discount_value' => ['required', 'numeric', 'gt:0'],
            'minimum_order_amount' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'discount_type' => $this->input('discount_type', $this->input('discountType')),
            'discount_value' => $this->input('discount_value', $this->input('discountValue')),
            'minimum_order_amount' => $this->input('minimum_order_amount', $this->input('minimumOrderAmount', $this->input('min_order_value'))),
            'start_date' => $this->input('start_date', $this->input('startDate')),
            'end_date' => $this->input('end_date', $this->input('endDate')),
            'is_active' => $this->input('is_active', $this->input('isActive', true)),
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('discount_type') === 'PERCENTAGE' && (float) $this->input('discount_value') > 100) {
                $validator->errors()->add('discount_value', 'Phần trăm giảm giá phải từ 0 đến 100');
            }
        });
    }
}
