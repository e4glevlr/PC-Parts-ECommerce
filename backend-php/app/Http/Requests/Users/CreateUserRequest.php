<?php

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;

class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'min:3', 'max:50'],
            'email' => ['required', 'email'],
            'password' => ['nullable', 'string', 'min:6'],
            'full_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
        ];
    }
}
