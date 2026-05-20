<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Cart\CartItemRequest;
use App\Http\Requests\Cart\MergeGuestCartRequest;
use App\Http\Resources\CartResource;
use App\Services\ApiResponseService;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController
{
    public function __construct(private ApiResponseService $response, private CartService $cartService)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $cart = $this->cartService->getOrCreate($request->attributes->get('current_user')->id);

        return $this->response->success('Lấy giỏ hàng thành công', (new CartResource($cart))->toArray($request));
    }

    public function addItem(CartItemRequest $request): JsonResponse
    {
        $cart = $this->cartService->addItem(
            $request->attributes->get('current_user')->id,
            $request->validated('product_id'),
            $request->validated('quantity')
        );

        return $this->response->success('Thêm sản phẩm thành công', (new CartResource($cart))->toArray($request));
    }

    public function updateItem(Request $request, int $item): JsonResponse
    {
        $cart = $this->cartService->updateItem($request->attributes->get('current_user')->id, $item, (int) $request->query('quantity', $request->input('quantity')));

        return $this->response->success('Cập nhật giỏ hàng thành công', (new CartResource($cart))->toArray($request));
    }

    public function removeItem(Request $request, int $item): JsonResponse
    {
        $cart = $this->cartService->removeItem($request->attributes->get('current_user')->id, $item);

        return $this->response->success('Xóa sản phẩm thành công', (new CartResource($cart))->toArray($request));
    }

    public function clear(Request $request): JsonResponse
    {
        $this->cartService->clear($request->attributes->get('current_user')->id);

        return $this->response->success('Xóa toàn bộ giỏ hàng thành công');
    }

    public function merge(MergeGuestCartRequest $request): JsonResponse
    {
        $cart = $this->cartService->merge($request->attributes->get('current_user')->id, $request->validated('guest_cart_items'));

        return $this->response->success('Hợp nhất giỏ hàng thành công', (new CartResource($cart))->toArray($request));
    }

    public function showForUser(Request $request, int $user): JsonResponse
    {
        $cart = $this->cartService->getOrCreate($user);

        return $this->response->success('Lấy giỏ hàng thành công', (new CartResource($cart))->toArray($request));
    }

    public function addItemForUser(CartItemRequest $request, int $user): JsonResponse
    {
        $cart = $this->cartService->addItem($user, $request->validated('product_id'), $request->validated('quantity'));

        return $this->response->success('Thêm sản phẩm thành công', (new CartResource($cart))->toArray($request));
    }

    public function updateItemForUser(Request $request, int $user, int $item): JsonResponse
    {
        $cart = $this->cartService->updateItem($user, $item, (int) $request->query('quantity', $request->input('quantity')));

        return $this->response->success('Cập nhật giỏ hàng thành công', (new CartResource($cart))->toArray($request));
    }

    public function removeItemForUser(Request $request, int $user, int $item): JsonResponse
    {
        $cart = $this->cartService->removeItem($user, $item);

        return $this->response->success('Xóa sản phẩm thành công', (new CartResource($cart))->toArray($request));
    }

    public function clearForUser(int $user): JsonResponse
    {
        $this->cartService->clear($user);

        return $this->response->success('Xóa toàn bộ giỏ hàng thành công');
    }
}
