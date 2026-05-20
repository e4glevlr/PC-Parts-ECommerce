<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Inventory\UpdateInventoryRequest;
use App\Http\Resources\InventoryLogResource;
use App\Http\Resources\ProductResource;
use App\Services\ApiResponseService;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController
{
    public function __construct(private ApiResponseService $response, private InventoryService $inventoryService)
    {
    }

    public function products(Request $request): JsonResponse
    {
        $page = max((int) $request->query('page', 0), 0);
        $size = min(max((int) $request->query('size', 20), 1), 100);
        [$items, $total] = $this->inventoryService->products($page, $size, $request->query('status'), $request->query('search'));
        $content = ProductResource::collection($items)->resolve($request);

        return $this->response->success('OK', $this->response->paginated($content, $total, $page, $size));
    }

    public function product(Request $request, int $product): JsonResponse
    {
        return $this->response->success('OK', (new ProductResource($this->inventoryService->product($product)))->toArray($request));
    }

    public function lowStock(Request $request): JsonResponse
    {
        $data = $this->inventoryService->lowStock((int) $request->query('threshold', 10));
        $data['products'] = ProductResource::collection($data['products'])->resolve($request);

        return $this->response->success('OK', $data);
    }

    public function outOfStock(Request $request): JsonResponse
    {
        return $this->response->success('OK', ProductResource::collection($this->inventoryService->outOfStock())->resolve($request));
    }

    public function needRestock(Request $request): JsonResponse
    {
        return $this->response->success('OK', ProductResource::collection($this->inventoryService->needRestock())->resolve($request));
    }

    public function adjust(UpdateInventoryRequest $request, int $product): JsonResponse
    {
        $data = $request->validated();
        $updated = $this->inventoryService->adjust($product, $data['change_type'], $data['quantity'], $data['reason'], $data['performed_by_id']);

        return $this->response->success('Cập nhật tồn kho thành công', (new ProductResource($updated))->toArray($request));
    }

    public function updateStock(UpdateInventoryRequest $request, int $product): JsonResponse
    {
        $data = $request->validated();
        $updated = $this->inventoryService->adjust($product, $data['change_type'], $data['quantity'], $data['reason'], $data['performed_by_id']);

        return $this->response->success('Cập nhật tồn kho thành công', [
            'product_id' => $product,
            'new_quantity' => $updated->quantity,
        ]);
    }

    public function threshold(Request $request, int $product): JsonResponse
    {
        $updated = $this->inventoryService->threshold($product, (int) $request->input('low_stock_threshold'));

        return $this->response->success('Cập nhật ngưỡng tồn kho thành công', (new ProductResource($updated))->toArray($request));
    }

    public function reserve(Request $request, int $product): JsonResponse
    {
        return $this->response->success('Giữ hàng thành công', [
            'product_id' => $product,
            'quantity' => (int) $request->input('quantity'),
            'reserved_for' => $request->input('reserved_for'),
        ]);
    }

    public function release(Request $request, int $product): JsonResponse
    {
        return $this->response->success('Hoàn hàng thành công', [
            'product_id' => $product,
            'quantity' => (int) $request->input('quantity'),
            'reason' => $request->input('reason'),
        ]);
    }

    public function logs(Request $request): JsonResponse
    {
        $page = max((int) $request->query('page', 0), 0);
        $size = min(max((int) $request->query('size', 20), 1), 100);
        [$items, $total] = $this->inventoryService->logs($page, $size, $request->query('change_type'), $request->query('date_from'), $request->query('date_to'), $request->query('search'));
        $content = InventoryLogResource::collection($items)->resolve($request);

        return $this->response->success('OK', $this->response->paginated($content, $total, $page, $size));
    }

    public function productLogs(Request $request, int $product): JsonResponse
    {
        $page = max((int) $request->query('page', 0), 0);
        $size = min(max((int) $request->query('size', 20), 1), 100);
        [$items, $total] = $this->inventoryService->productLogs($product, $page, $size);
        $content = InventoryLogResource::collection($items)->resolve($request);

        return $this->response->success('OK', $this->response->paginated($content, $total, $page, $size));
    }

    public function availability(int $product): JsonResponse
    {
        $item = $this->inventoryService->product($product);

        return $this->response->success('OK', [
            'product_id' => $item->id,
            'quantity' => $item->quantity,
            'available' => $item->quantity > 0,
            'is_low_stock' => $item->is_low_stock,
            'is_out_of_stock' => $item->quantity === 0,
        ]);
    }
}
