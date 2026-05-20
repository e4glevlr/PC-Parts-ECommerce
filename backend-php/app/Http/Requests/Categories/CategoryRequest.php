<?php

namespace App\Http\Requests\Categories;

use Illuminate\Foundation\Http\FormRequest;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'parent_category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
