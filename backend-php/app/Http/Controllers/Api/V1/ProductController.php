<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Products\ProductRequest;
use App\Http\Requests\Products\ProductWithImageUrlsRequest;
use App\Http\Resources\ProductResource;
use App\Services\ApiResponseService;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController
{
    public function __construct(private ApiResponseService $response, private ProductService $productService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $this->productService->parseFilters($request->query());
        [$items, $total] = $this->productService->list($filters);
        $content = ProductResource::collection($items)->resolve($request);

        return $this->response->success('Thành công', $this->response->paginated($content, $total, $filters['page'], $filters['size']));
    }

    public function management(Request $request): JsonResponse
    {
        $page = max((int) $request->query('page', 0), 0);
        $size = min(max((int) $request->query('size', 20), 1), 100);
        [$items, $total] = $this->productService->management([
            'page' => $page,
            'size' => $size,
            'category_id' => $request->query('category_id'),
            'stock_status' => $request->query('stock_status'),
            'search' => $request->query('search'),
        ]);
        $content = ProductResource::collection($items)->resolve($request);

        return $this->response->success('Thành công', $this->response->paginated($content, $total, $page, $size));
    }

    public function count(): JsonResponse
    {
        return $this->response->success('OK', $this->productService->countActive());
    }

    public function search(Request $request): JsonResponse
    {
        $page = max((int) $request->query('page', 0), 0);
        $size = min(max((int) $request->query('size', 20), 1), 100);
        [$items, $total] = $this->productService->search((string) $request->query('keyword', ''), $page, $size);
        $content = ProductResource::collection($items)->resolve($request);

        return $this->response->success('Thành công', $this->response->paginated($content, $total, $page, $size));
    }

    public function byCategory(Request $request, int $category): JsonResponse
    {
        $page = max((int) $request->query('page', 0), 0);
        $size = min(max((int) $request->query('size', 20), 1), 100);
        [$items, $total] = $this->productService->byCategory($category, $page, $size);
        $content = ProductResource::collection($items)->resolve($request);

        return $this->response->success('Thành công', $this->response->paginated($content, $total, $page, $size));
    }

    public function show(Request $request, int $product): JsonResponse
    {
        return $this->response->success('Thành công', (new ProductResource($this->productService->find($product)))->toArray($request));
    }

    public function store(ProductRequest $request): JsonResponse
    {
        $product = $this->productService->create($request->validated());

        return $this->response->success('Tạo sản phẩm thành công', (new ProductResource($product))->toArray($request), 201);
    }

    public function createWithImageUrls(ProductWithImageUrlsRequest $request): JsonResponse
    {
        $product = $this->productService->createWithImageUrls($request->validated());

        return $this->response->success('Tạo sản phẩm thành công', (new ProductResource($product))->toArray($request), 201);
    }

    public function update(ProductRequest $request, int $product): JsonResponse
    {
        $updated = $this->productService->update($product, $request->validated());

        return $this->response->success('Cập nhật sản phẩm thành công', (new ProductResource($updated))->toArray($request));
    }

    public function destroy(int $product): JsonResponse
    {
        $this->productService->delete($product);

        return $this->response->success('Xóa sản phẩm thành công');
    }
}
