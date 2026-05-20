<?php

namespace App\Http\Requests\Products;

class ProductWithImageUrlsRequest extends ProductRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'image_urls' => ['nullable', 'array'],
            'image_urls.*' => ['required', 'string', 'max:500'],
        ]);
    }
}
