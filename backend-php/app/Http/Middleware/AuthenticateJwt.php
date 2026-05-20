<?php

namespace App\Http\Middleware;

use App\Exceptions\UnauthorizedException;
use App\Models\Token;
use App\Models\User;
use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateJwt
{
    public function __construct(private JwtService $jwtService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization');

        if (!$header || !str_starts_with($header, 'Bearer ')) {
            throw new UnauthorizedException('Yêu cầu xác thực');
        }

        $token = substr($header, 7);
        $payload = $this->jwtService->decode($token);

        if (!$payload || ($payload['type'] ?? null) !== 'access') {
            throw new UnauthorizedException('Token không hợp lệ hoặc đã hết hạn');
        }

        $storedToken = Token::where('token', $token)->where('revoked', false)->where('expired', false)->first();

        if (!$storedToken) {
            throw new UnauthorizedException('Token đã bị thu hồi');
        }

        $user = User::with('role')->where('id', $payload['user_id'] ?? null)->where('is_active', true)->first();

        if (!$user) {
            throw new UnauthorizedException('Người dùng không tồn tại hoặc đã bị khóa');
        }

        $request->attributes->set('current_user', $user);
        $request->attributes->set('access_token', $token);

        return $next($request);
    }
}
