<?php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;

class ProductRequest extends FormRequest
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
            'price' => ['required', 'numeric', 'gt:0'],
            'quantity' => ['nullable', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'specifications' => ['nullable', 'array'],
            'attributes' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
