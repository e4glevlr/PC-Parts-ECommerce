<?php

namespace App\Http\Requests\Categories;

use Illuminate\Foundation\Http\FormRequest;

class AttributeDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'display_name' => ['required', 'string', 'max:200'],
            'data_type' => ['required', 'string', 'max:20'],
            'input_type' => ['required', 'string', 'max:30'],
            'unit' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer'],
            'options' => ['nullable'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
