<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'access_token' => $this['access_token'],
            'refresh_token' => $this['refresh_token'] ?? null,
            'token_type' => $this['token_type'] ?? 'Bearer',
            'expires_in' => $this['expires_in'] ?? null,
            'user' => isset($this['user']) ? (new UserResource($this['user']))->toArray($request) : null,
        ];
    }
}
