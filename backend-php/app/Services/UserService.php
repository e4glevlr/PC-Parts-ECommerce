<?php

namespace App\Services;

use App\Exceptions\BadRequestException;
use App\Exceptions\ResourceNotFoundException;
use App\Models\Role;
use App\Models\User;

class UserService
{
    public function __construct(private AuthService $authService)
    {
    }

    public function list(int $page, int $size, ?string $search): array
    {
        $query = User::with('role');

        if ($search) {
            $query->where(function ($builder) use ($search) {
                $keyword = "%{$search}%";
                $builder->where('username', 'ilike', $keyword)
                    ->orWhere('email', 'ilike', $keyword)
                    ->orWhere('phone', 'ilike', $keyword)
                    ->orWhere('full_name', 'ilike', $keyword);
            });
        }

        $total = $query->count();
        $items = $query->offset($page * $size)->limit($size)->get();

        return [$items, $total];
    }

    public function find(int $id): User
    {
        return $this->authService->findUser($id);
    }

    public function findByUsername(string $username): User
    {
        $user = User::with('role')->where('username', $username)->first();

        if (!$user) {
            throw new ResourceNotFoundException('Người dùng', 'username', $username);
        }

        return $user;
    }

    public function byRole(string $roleName)
    {
        $role = Role::whereRaw('upper(name) = ?', [strtoupper($roleName)])->first();

        if (!$role) {
            throw new BadRequestException("Không tìm thấy vai trò: {$roleName}");
        }

        return User::with('role')->where('role_id', $role->id)->get();
    }

    public function updateProfile(User $user, array $data): User
    {
        $phone = $this->authService->normalizePhone($data['phone'] ?? null);

        if (($data['email'] ?? null) !== $user->email && User::where('email', $data['email'])->exists()) {
            throw new BadRequestException('Email đã tồn tại');
        }

        if ($phone && $phone !== $user->phone && User::where('phone', $phone)->exists()) {
            throw new BadRequestException('Số điện thoại đã tồn tại');
        }

        $user->update([
            'email' => $data['email'],
            'full_name' => $data['full_name'],
            'phone' => $phone,
            'address' => $data['address'] ?? null,
        ]);

        return $user->refresh()->load('role');
    }

    public function changePassword(User $user, string $oldPassword, string $newPassword): void
    {
        if (!$user->is_active) {
            throw new BadRequestException('Tài khoản đã bị khóa');
        }

        if (!password_verify($oldPassword, $user->password)) {
            throw new BadRequestException('Mật khẩu hiện tại không đúng');
        }

        $user->update(['password' => password_hash($newPassword, PASSWORD_BCRYPT)]);
    }

    public function update(int $id, array $data): User
    {
        $user = $this->find($id);
        $payload = array_filter([
            'full_name' => $data['full_name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => isset($data['phone']) ? $this->authService->normalizePhone($data['phone']) : null,
            'address' => $data['address'] ?? null,
            'role_id' => $data['role_id'] ?? null,
            'is_active' => $data['is_active'] ?? null,
        ], fn ($value) => $value !== null);

        if (isset($data['password']) && $data['password']) {
            $payload['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }

        $user->update($payload);

        return $user->refresh()->load('role');
    }

    public function delete(int $id): void
    {
        $this->find($id)->update(['is_active' => false]);
    }

    public function usernameExists(string $username): bool
    {
        return User::where('username', $username)->exists();
    }

    public function emailExists(string $email): bool
    {
        return User::where('email', $email)->exists();
    }

    public function count(): int
    {
        return User::count();
    }
}
