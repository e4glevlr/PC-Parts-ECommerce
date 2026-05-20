<?php

namespace App\Services;

use App\Exceptions\BadRequestException;
use App\Exceptions\ResourceNotFoundException;
use App\Exceptions\UnauthorizedException;
use App\Models\Role;
use App\Models\Token;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AuthService
{
    public function __construct(private JwtService $jwtService)
    {
    }

    public function normalizePhone(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        $value = preg_replace('/[\s-]/', '', trim($phone));

        if (str_starts_with($value, '+84')) {
            return '0'.substr($value, 3);
        }

        if (str_starts_with($value, '84') && strlen($value) > 2 && $value[2] !== '0') {
            return '0'.substr($value, 2);
        }

        return $value;
    }

    public function login(string $identifier, string $password): array
    {
        $user = User::with('role')
            ->where('username', $identifier)
            ->orWhere('email', $identifier)
            ->orWhere('phone', $this->normalizePhone($identifier))
            ->first();

        if (!$user || !password_verify($password, $user->password)) {
            throw new BadRequestException('Tên đăng nhập hoặc mật khẩu không đúng');
        }

        if (!$user->is_active) {
            throw new BadRequestException('Tài khoản đã bị khóa');
        }

        return $this->issueTokens($user);
    }

    public function register(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $user = $this->createUser($data);
            return $this->issueTokens($user->load('role'));
        });
    }

    public function createUser(array $data): User
    {
        $phone = $this->normalizePhone($data['phone'] ?? null);

        if (User::where('username', $data['username'])->exists()) {
            throw new BadRequestException('Username đã tồn tại');
        }

        if (User::where('email', $data['email'])->exists()) {
            throw new BadRequestException('Email đã tồn tại');
        }

        if ($phone && User::where('phone', $phone)->exists()) {
            throw new BadRequestException('Số điện thoại đã tồn tại');
        }

        $roleId = $data['role_id'] ?? null;

        if (!$roleId) {
            $role = Role::where('name', 'CUSTOMER')->first();

            if (!$role) {
                throw new BadRequestException('Default role CUSTOMER not found');
            }

            $roleId = $role->id;
        }

        return User::create([
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_BCRYPT),
            'full_name' => $data['full_name'],
            'phone' => $phone,
            'address' => $data['address'] ?? null,
            'role_id' => $roleId,
            'is_active' => true,
        ])->load('role');
    }

    public function refreshToken(string $refreshToken): array
    {
        $payload = $this->jwtService->decode($refreshToken);

        if (!$payload || ($payload['type'] ?? null) !== 'refresh') {
            throw new UnauthorizedException('Token không hợp lệ hoặc đã hết hạn');
        }

        $user = User::with('role')->where('id', $payload['user_id'] ?? null)->where('is_active', true)->first();

        if (!$user) {
            throw new UnauthorizedException('Người dùng không tồn tại hoặc đã bị khóa');
        }

        $accessToken = $this->jwtService->createAccessToken($user);

        Token::create([
            'user_id' => $user->id,
            'token' => $accessToken,
            'token_type' => 'ACCESS_TOKEN',
            'expiration_date' => Carbon::now()->addSeconds(config('shop.jwt_expiration_seconds')),
            'revoked' => false,
            'expired' => false,
        ]);

        return [
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
        ];
    }

    public function logout(string $token): void
    {
        Token::where('token', $token)->update([
            'expired' => true,
            'revoked' => true,
        ]);
    }

    public function issueTokens(User $user): array
    {
        $accessToken = $this->jwtService->createAccessToken($user);
        $refreshToken = $this->jwtService->createRefreshToken($user);

        Token::create([
            'user_id' => $user->id,
            'token' => $accessToken,
            'token_type' => 'ACCESS_TOKEN',
            'expiration_date' => Carbon::now()->addSeconds(config('shop.jwt_expiration_seconds')),
            'revoked' => false,
            'expired' => false,
        ]);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => config('shop.jwt_expiration_seconds'),
            'user' => $user,
        ];
    }

    public function findUser(int $id): User
    {
        $user = User::with('role')->find($id);

        if (!$user) {
            throw new ResourceNotFoundException('Người dùng', 'id', $id);
        }

        return $user;
    }
}
