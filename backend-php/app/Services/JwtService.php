<?php

namespace App\Services;

use App\Models\User;
use Throwable;

class JwtService
{
    public function createAccessToken(User $user): string
    {
        return $this->encode([
            'sub' => $user->username,
            'user_id' => $user->id,
            'role' => $user->role->name,
            'exp' => time() + config('shop.jwt_expiration_seconds'),
            'type' => 'access',
        ]);
    }

    public function createRefreshToken(User $user): string
    {
        return $this->encode([
            'sub' => $user->username,
            'user_id' => $user->id,
            'exp' => time() + config('shop.jwt_refresh_expiration_seconds'),
            'type' => 'refresh',
        ]);
    }

    public function decode(string $token): ?array
    {
        try {
            $parts = explode('.', $token);

            if (count($parts) !== 3) {
                return null;
            }

            [$header, $payload, $signature] = $parts;
            $decodedHeader = json_decode($this->base64UrlDecode($header), true);
            $decodedPayload = json_decode($this->base64UrlDecode($payload), true);

            if (!$decodedHeader || !$decodedPayload) {
                return null;
            }

            if (($decodedHeader['alg'] ?? null) !== config('shop.jwt_algorithm')) {
                return null;
            }

            $expected = $this->sign("{$header}.{$payload}");

            if (!hash_equals($expected, $signature)) {
                return null;
            }

            if (isset($decodedPayload['exp']) && time() >= (int) $decodedPayload['exp']) {
                return null;
            }

            return $decodedPayload;
        } catch (Throwable) {
            return null;
        }
    }

    private function encode(array $payload): string
    {
        $header = [
            'typ' => 'JWT',
            'alg' => config('shop.jwt_algorithm'),
        ];
        $encodedHeader = $this->base64UrlEncode(json_encode($header));
        $encodedPayload = $this->base64UrlEncode(json_encode($payload));
        $signature = $this->sign("{$encodedHeader}.{$encodedPayload}");

        return "{$encodedHeader}.{$encodedPayload}.{$signature}";
    }

    private function sign(string $value): string
    {
        return $this->base64UrlEncode(hash_hmac('sha256', $value, config('shop.jwt_secret'), true));
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        return base64_decode(strtr($value, '-_', '+/'));
    }
}
