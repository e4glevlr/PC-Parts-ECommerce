<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Promotions\PromotionRequest;
use App\Http\Resources\PromotionResource;
use App\Services\ApiResponseService;
use App\Services\PromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromotionController
{
    public function __construct(private ApiResponseService $response, private PromotionService $promotionService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $page = max((int) $request->query('page', 0), 0);
        $size = min(max((int) $request->query('size', 20), 1), 100);
        $isActive = $request->has('is_active') ? filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN) : null;
        [$items, $total] = $this->promotionService->list($page, $size, $request->query('status'), $request->query('discount_type'), $request->query('search'), $isActive);
        $content = PromotionResource::collection($items)->resolve($request);

        return $this->response->success('OK', $this->response->paginated($content, $total, $page, $size));
    }

    public function active(Request $request): JsonResponse
    {
        return $this->response->success('OK', PromotionResource::collection($this->promotionService->active())->resolve($request));
    }

    public function applicable(Request $request): JsonResponse
    {
        $price = (float) $request->query('price', 0);

        return $this->response->success('OK', PromotionResource::collection($this->promotionService->applicable($price))->resolve($request));
    }

    public function best(Request $request): JsonResponse
    {
        $price = (float) $request->query('price', 0);
        $promotion = $this->promotionService->best($price);

        return $this->response->success('OK', $promotion ? (new PromotionResource($promotion))->toArray($request) : null);
    }

    public function show(Request $request, int $promotion): JsonResponse
    {
        return $this->response->success('OK', (new PromotionResource($this->promotionService->find($promotion)))->toArray($request));
    }

    public function store(PromotionRequest $request): JsonResponse
    {
        $promotion = $this->promotionService->create($request->validated());

        return $this->response->success('Tạo khuyến mãi thành công', (new PromotionResource($promotion))->toArray($request), 201);
    }

    public function update(PromotionRequest $request, int $promotion): JsonResponse
    {
        $updated = $this->promotionService->update($promotion, $request->validated());

        return $this->response->success('Cập nhật khuyến mãi thành công', (new PromotionResource($updated))->toArray($request));
    }

    public function destroy(int $promotion): JsonResponse
    {
        $this->promotionService->delete($promotion);

        return $this->response->success('Xóa khuyến mãi thành công');
    }

    public function activate(Request $request, int $promotion): JsonResponse
    {
        return $this->response->success('Kích hoạt thành công', (new PromotionResource($this->promotionService->activate($promotion)))->toArray($request));
    }

    public function deactivate(Request $request, int $promotion): JsonResponse
    {
        return $this->response->success('Vô hiệu hóa thành công', (new PromotionResource($this->promotionService->deactivate($promotion)))->toArray($request));
    }

    public function calculateDiscount(Request $request, int $promotion): JsonResponse
    {
        $item = $this->promotionService->find($promotion);
        $price = (float) $request->query('originalPrice', $request->query('original_price', 0));

        return $this->response->success('OK', [
            'promotionId' => $item->id,
            'promotion_id' => $item->id,
            'amount' => $this->promotionService->calculateDiscount($item, $price),
        ]);
    }
}
