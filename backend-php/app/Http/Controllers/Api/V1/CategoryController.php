<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Categories\AttributeDefinitionRequest;
use App\Http\Requests\Categories\CategoryRequest;
use App\Http\Resources\AttributeDefinitionResource;
use App\Http\Resources\CategoryResource;
use App\Services\ApiResponseService;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController
{
    public function __construct(private ApiResponseService $response, private CategoryService $categoryService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->response->success('OK', CategoryResource::collection($this->categoryService->active())->resolve($request));
    }

    public function show(Request $request, int $category): JsonResponse
    {
        return $this->response->success('OK', (new CategoryResource($this->categoryService->find($category)))->toArray($request));
    }

    public function store(CategoryRequest $request): JsonResponse
    {
        $category = $this->categoryService->create($request->validated());

        return $this->response->success('Tạo danh mục thành công', (new CategoryResource($category))->toArray($request), 201);
    }

    public function update(CategoryRequest $request, int $category): JsonResponse
    {
        $updated = $this->categoryService->update($category, $request->validated());

        return $this->response->success('Cập nhật danh mục thành công', (new CategoryResource($updated))->toArray($request));
    }

    public function destroy(int $category): JsonResponse
    {
        $this->categoryService->delete($category);

        return $this->response->success('Xóa danh mục thành công');
    }

    public function tree(Request $request): JsonResponse
    {
        return $this->response->success('OK', CategoryResource::collection($this->categoryService->tree())->resolve($request));
    }

    public function byParent(Request $request, int $parent): JsonResponse
    {
        return $this->response->success('OK', CategoryResource::collection($this->categoryService->byParent($parent))->resolve($request));
    }

    public function filters(Request $request, int $category): JsonResponse
    {
        return $this->response->success('OK', AttributeDefinitionResource::collection($this->categoryService->attributes($category))->resolve($request));
    }

    public function createAttribute(AttributeDefinitionRequest $request, int $category): JsonResponse
    {
        $attribute = $this->categoryService->createAttribute($category, $request->validated());

        return $this->response->success('Tạo thuộc tính thành công', (new AttributeDefinitionResource($attribute))->toArray($request), 201);
    }

    public function updateAttribute(AttributeDefinitionRequest $request, int $category, int $attribute): JsonResponse
    {
        $updated = $this->categoryService->updateAttribute($category, $attribute, $request->validated());

        return $this->response->success('Cập nhật thuộc tính thành công', (new AttributeDefinitionResource($updated))->toArray($request));
    }

    public function deleteAttribute(int $category, int $attribute): JsonResponse
    {
        $this->categoryService->deleteAttribute($category, $attribute);

        return $this->response->success('Xóa thuộc tính thành công');
    }
}
