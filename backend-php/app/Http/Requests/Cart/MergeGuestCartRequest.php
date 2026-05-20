<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class MergeGuestCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guest_cart_items' => ['required', 'array'],
            'guest_cart_items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'guest_cart_items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
