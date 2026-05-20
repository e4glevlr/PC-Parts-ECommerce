<?php

namespace App\Services;

use App\Exceptions\BadRequestException;
use App\Exceptions\ResourceNotFoundException;
use App\Models\AttributeDefinition;
use App\Models\Category;

class CategoryService
{
    public function active()
    {
        return Category::with('parentCategory')->where('is_active', true)->get();
    }

    public function find(int $id): Category
    {
        $category = Category::with(['parentCategory', 'children'])->find($id);

        if (!$category) {
            throw new ResourceNotFoundException('Danh mục', 'id', $id);
        }

        return $category;
    }

    public function create(array $data): Category
    {
        return Category::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'parent_category_id' => $data['parent_category_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ])->load('parentCategory');
    }

    public function update(int $id, array $data): Category
    {
        $category = $this->find($id);
        $category->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'parent_category_id' => $data['parent_category_id'] ?? $category->parent_category_id,
            'is_active' => $data['is_active'] ?? $category->is_active,
        ]);

        return $category->refresh()->load('parentCategory');
    }

    public function delete(int $id): void
    {
        $this->find($id)->update(['is_active' => false]);
    }

    public function tree()
    {
        return Category::with(['children' => fn ($query) => $query->where('is_active', true)->with('children')])
            ->whereNull('parent_category_id')
            ->where('is_active', true)
            ->get();
    }

    public function byParent(int $parentId)
    {
        return Category::with('parentCategory')
            ->where('parent_category_id', $parentId)
            ->where('is_active', true)
            ->get();
    }

    public function attributes(int $categoryId)
    {
        $this->find($categoryId);

        return AttributeDefinition::where('category_id', $categoryId)
            ->where('is_active', true)
            ->orderByRaw('coalesce(sort_order, 9999)')
            ->get();
    }

    public function createAttribute(int $categoryId, array $data): AttributeDefinition
    {
        $this->find($categoryId);

        $exists = AttributeDefinition::where('category_id', $categoryId)
            ->whereRaw('lower(code) = ?', [strtolower($data['code'])])
            ->exists();

        if ($exists) {
            throw new BadRequestException("Attribute code '{$data['code']}' đã tồn tại cho danh mục này");
        }

        return AttributeDefinition::create(array_merge($data, ['category_id' => $categoryId]));
    }

    public function updateAttribute(int $categoryId, int $attributeId, array $data): AttributeDefinition
    {
        $attribute = AttributeDefinition::where('category_id', $categoryId)->where('id', $attributeId)->first();

        if (!$attribute) {
            throw new ResourceNotFoundException('Thuộc tính', 'id', $attributeId);
        }

        $attribute->update($data);

        return $attribute->refresh();
    }

    public function deleteAttribute(int $categoryId, int $attributeId): void
    {
        $attribute = AttributeDefinition::where('category_id', $categoryId)->where('id', $attributeId)->first();

        if (!$attribute) {
            throw new ResourceNotFoundException('Thuộc tính', 'id', $attributeId);
        }

        $attribute->update(['is_active' => false]);
    }
}
