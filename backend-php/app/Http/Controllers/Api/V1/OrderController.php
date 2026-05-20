<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ForbiddenException;
use App\Http\Requests\Orders\CreateOrderFromCartRequest;
use App\Http\Requests\Orders\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Services\ApiResponseService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController
{
    public function __construct(private ApiResponseService $response, private OrderService $orderService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $page = max((int) $request->query('page', 0), 0);
        $size = min(max((int) $request->query('size', 20), 1), 100);
        [$items, $total] = $this->orderService->list($page, $size, $request->query('search'));
        $content = OrderResource::collection($items)->resolve($request);

        return $this->response->success('Thành công', $this->response->paginated($content, $total, $page, $size));
    }

    public function myOrders(Request $request): JsonResponse
    {
        $page = max((int) $request->query('page', 0), 0);
        $size = min(max((int) $request->query('size', 20), 1), 100);
        [$items, $total] = $this->orderService->byUser($request->attributes->get('current_user')->id, $page, $size, $request->query('status'));
        $content = OrderResource::collection($items)->resolve($request);

        return $this->response->success('Thành công', $this->response->paginated($content, $total, $page, $size));
    }

    public function byUser(Request $request): JsonResponse
    {
        $page = max((int) $request->query('page', 0), 0);
        $size = min(max((int) $request->query('size', 20), 1), 100);
        [$items, $total] = $this->orderService->byUser((int) $request->input('user_id'), $page, $size, $request->query('status'));
        $content = OrderResource::collection($items)->resolve($request);

        return $this->response->success('Thành công', $this->response->paginated($content, $total, $page, $size));
    }

    public function byUserLegacy(Request $request, int $user): JsonResponse
    {
        $currentUser = $request->attributes->get('current_user');

        if (!in_array($currentUser->role->name, ['ADMIN', 'STAFF'], true) && $currentUser->id !== $user) {
            throw new ForbiddenException();
        }

        $page = max((int) $request->query('page', 0), 0);
        $size = min(max((int) $request->query('size', 20), 1), 100);
        [$items, $total] = $this->orderService->byUser($user, $page, $size, $request->query('status'));
        $content = OrderResource::collection($items)->resolve($request);

        return $this->response->success('Thành công', $this->response->paginated($content, $total, $page, $size));
    }

    public function byCode(Request $request, string $code): JsonResponse
    {
        return $this->response->success('Thành công', (new OrderResource($this->orderService->byCode($code)))->toArray($request));
    }

    public function byStatus(Request $request, string $status): JsonResponse
    {
        $page = max((int) $request->query('page', 0), 0);
        $size = min(max((int) $request->query('size', 20), 1), 100);
        [$items, $total] = $this->orderService->byStatus($status, $page, $size);
        $content = OrderResource::collection($items)->resolve($request);

        return $this->response->success('Thành công', $this->response->paginated($content, $total, $page, $size));
    }

    public function stats(): JsonResponse
    {
        return $this->response->success('OK', $this->orderService->stats());
    }

    public function show(Request $request, int $order): JsonResponse
    {
        $found = $this->orderService->find($order);
        $this->orderService->assertVisibleTo($request->attributes->get('current_user'), $found);

        return $this->response->success('Thành công', (new OrderResource($found))->toArray($request));
    }

    public function createFromCart(CreateOrderFromCartRequest $request): JsonResponse
    {
        $order = $this->orderService->createFromCart($request->attributes->get('current_user')->id, $request->validated());

        return $this->response->success('Tạo đơn hàng thành công', (new OrderResource($order))->toArray($request), 201);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, int $order): JsonResponse
    {
        $updated = $this->orderService->updateStatus($order, $request->validated('status'));

        return $this->response->success('Cập nhật trạng thái thành công', (new OrderResource($updated))->toArray($request));
    }

    public function cancel(Request $request, int $order): JsonResponse
    {
        $found = $this->orderService->find($order);
        $this->orderService->assertVisibleTo($request->attributes->get('current_user'), $found);
        $cancelled = $this->orderService->cancel($order);

        return $this->response->success('Hủy đơn hàng thành công', (new OrderResource($cancelled))->toArray($request));
    }
}
