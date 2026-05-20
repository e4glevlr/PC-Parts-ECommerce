<?php

namespace App\Http\Requests\Orders;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderFromCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shipping_address' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'shipping_phone' => ['nullable', 'string', 'max:20'],
            'customer_name' => ['nullable', 'string', 'max:100'],
            'customer_email' => ['nullable', 'string', 'max:100'],
            'payment_method' => ['nullable', 'string', 'max:20'],
            'promotion_id' => ['nullable', 'integer', 'exists:promotions,id'],
        ];
    }
}
