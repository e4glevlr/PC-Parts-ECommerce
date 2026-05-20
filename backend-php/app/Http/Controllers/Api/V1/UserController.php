<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Users\ChangePasswordRequest;
use App\Http\Requests\Users\CreateUserRequest;
use App\Http\Requests\Users\LoginRequest;
use App\Http\Requests\Users\RegisterRequest;
use App\Http\Requests\Users\UpdateProfileRequest;
use App\Http\Resources\AuthResource;
use App\Http\Resources\UserResource;
use App\Services\ApiResponseService;
use App\Services\AuthService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController
{
    public function __construct(
        private ApiResponseService $response,
        private AuthService $authService,
        private UserService $userService
    ) {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated('identifier'), $request->validated('password'));

        return $this->response->success('Đăng nhập thành công', (new AuthResource($result))->toArray($request));
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return $this->response->success('Đăng ký thành công', (new AuthResource($result))->toArray($request), 201);
    }

    public function refreshToken(Request $request): JsonResponse
    {
        $token = $request->query('refreshToken', $request->input('refresh_token', $request->input('refreshToken')));
        $result = $this->authService->refreshToken($token);

        return $this->response->success('Làm mới token thành công', $result);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->attributes->get('access_token'));

        return $this->response->success('Đăng xuất thành công');
    }

    public function profile(Request $request): JsonResponse
    {
        return $this->response->success('Lấy profile thành công', (new UserResource($request->attributes->get('current_user')))->toArray($request));
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->userService->updateProfile($request->attributes->get('current_user'), $request->validated());

        return $this->response->success('Cập nhật profile thành công', (new UserResource($user))->toArray($request));
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->userService->changePassword(
            $request->attributes->get('current_user'),
            $request->validated('old_password'),
            $request->validated('new_password')
        );

        return $this->response->success('Đổi mật khẩu thành công');
    }

    public function create(CreateUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['password'] = $data['password'] ?? 'default123';
        $user = $this->authService->createUser($data);

        return $this->response->success('Tạo người dùng thành công', (new UserResource($user))->toArray($request), 201);
    }

    public function index(Request $request): JsonResponse
    {
        $page = max((int) $request->query('page', 0), 0);
        $size = min(max((int) $request->query('size', 20), 1), 100);
        [$items, $total] = $this->userService->list($page, $size, $request->query('search'));
        $content = UserResource::collection($items)->resolve($request);

        return $this->response->success('Lấy danh sách người dùng thành công', $this->response->paginated($content, $total, $page, $size));
    }

    public function show(Request $request, int $user): JsonResponse
    {
        $found = $this->userService->find($user);

        return $this->response->success('Lấy thông tin người dùng thành công', (new UserResource($found))->toArray($request));
    }

    public function update(Request $request, int $user): JsonResponse
    {
        $updated = $this->userService->update($user, $request->all());

        return $this->response->success('Cập nhật người dùng thành công', (new UserResource($updated))->toArray($request));
    }

    public function destroy(int $user): JsonResponse
    {
        $this->userService->delete($user);

        return $this->response->success('Xóa người dùng thành công');
    }

    public function byRole(Request $request, string $role): JsonResponse
    {
        return $this->response->success('Thành công', UserResource::collection($this->userService->byRole($role))->resolve($request));
    }

    public function checkUsername(string $username): JsonResponse
    {
        return $this->response->success('OK', ['exists' => $this->userService->usernameExists($username)]);
    }

    public function checkEmail(string $email): JsonResponse
    {
        return $this->response->success('OK', ['exists' => $this->userService->emailExists($email)]);
    }

    public function byUsername(Request $request, string $username): JsonResponse
    {
        return $this->response->success('Thành công', (new UserResource($this->userService->findByUsername($username)))->toArray($request));
    }

    public function count(): JsonResponse
    {
        return $this->response->success('OK', $this->userService->count());
    }
}
