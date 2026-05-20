<?php

namespace App\Services;

use App\Exceptions\BadRequestException;
use App\Exceptions\ResourceNotFoundException;
use App\Models\AttributeDefinition;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

class ProductService
{
    public function list(array $filters): array
    {
        $query = Product::with(['category', 'images'])->where('is_active', true);
        $this->applyFilters($query, $filters);
        $this->applySorting($query, $filters);

        $page = $filters['page'];
        $size = $filters['size'];
        $total = $query->count();
        $items = $query->offset($page * $size)->limit($size)->get();

        return [$items, $total];
    }

    public function management(array $filters): array
    {
        $query = Product::with(['category', 'images']);

        if ($filters['category_id']) {
            $query->where('category_id', $filters['category_id']);
        }

        if ($filters['stock_status']) {
            $status = strtolower($filters['stock_status']);

            if ($status === 'in_stock') {
                $query->where('quantity', '>', 0);
            } elseif ($status === 'out_of_stock') {
                $query->where('quantity', 0);
            } elseif ($status === 'low_stock') {
                $query->where('quantity', '>', 0)->whereColumn('quantity', '<=', 'low_stock_threshold');
            }
        }

        if ($filters['search']) {
            $this->applySearch($query, $filters['search']);
        }

        $page = $filters['page'];
        $size = $filters['size'];
        $total = $query->count();
        $items = $query->orderByDesc('created_at')->offset($page * $size)->limit($size)->get();

        return [$items, $total];
    }

    public function find(int $id): Product
    {
        $product = Product::with(['category', 'images'])->find($id);

        if (!$product) {
            throw new ResourceNotFoundException('Sản phẩm', 'id', $id);
        }

        return $product;
    }

    public function byCategory(int $categoryId, int $page, int $size): array
    {
        $query = Product::with(['category', 'images'])->where('category_id', $categoryId)->where('is_active', true);
        $total = $query->count();
        $items = $query->offset($page * $size)->limit($size)->get();

        return [$items, $total];
    }

    public function search(string $keyword, int $page, int $size): array
    {
        $query = Product::with(['category', 'images'])->where('is_active', true);
        $this->applySearch($query, $keyword);
        $total = $query->count();
        $items = $query->offset($page * $size)->limit($size)->get();

        return [$items, $total];
    }

    public function countActive(): int
    {
        return Product::where('is_active', true)->count();
    }

    public function create(array $data): Product
    {
        $category = Category::find($data['category_id']);

        if (!$category) {
            throw new ResourceNotFoundException('Danh mục', 'id', $data['category_id']);
        }

        if (!$category->is_active) {
            throw new BadRequestException('Danh mục hiện không hoạt động');
        }

        $product = Product::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'quantity' => $data['quantity'] ?? 0,
            'low_stock_threshold' => $data['low_stock_threshold'] ?? config('shop.default_low_stock_threshold'),
            'category_id' => $data['category_id'],
            'specifications' => $data['specifications'] ?? null,
            'attributes' => $data['attributes'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return $product->load(['category', 'images']);
    }

    public function createWithImageUrls(array $data): Product
    {
        $urls = $data['image_urls'] ?? [];
        unset($data['image_urls']);
        $product = $this->create($data);

        foreach ($urls as $index => $url) {
            ProductImage::create([
                'product_id' => $product->id,
                'image_url' => $url,
                'is_primary' => $index === 0,
                'created_at' => Carbon::now(),
            ]);
        }

        return $product->refresh()->load(['category', 'images']);
    }

    public function update(int $id, array $data): Product
    {
        $product = $this->find($id);

        if (isset($data['category_id']) && !Category::where('id', $data['category_id'])->exists()) {
            throw new ResourceNotFoundException('Danh mục', 'id', $data['category_id']);
        }

        $product->update(Arr::only($data, [
            'name',
            'description',
            'price',
            'quantity',
            'low_stock_threshold',
            'category_id',
            'specifications',
            'attributes',
            'is_active',
        ]));

        return $product->refresh()->load(['category', 'images']);
    }

    public function delete(int $id): void
    {
        $this->find($id)->update(['is_active' => false]);
    }

    public function updateStock(int $productId, int $newQuantity, string $reason, int $performedBy): Product
    {
        $product = $this->find($productId);
        $oldQuantity = $product->quantity;
        $change = $newQuantity - $oldQuantity;
        $changeType = $change > 0 ? 'IN' : 'OUT';

        $product->update(['quantity' => $newQuantity]);
        $product->inventoryLogs()->create([
            'change_type' => $changeType,
            'quantity_change' => abs($change),
            'reason' => $reason,
            'performed_by' => $performedBy,
            'created_at' => Carbon::now(),
        ]);

        return $product->refresh()->load(['category', 'images']);
    }

    public function attributeDefinitions(int $categoryId)
    {
        return AttributeDefinition::where('category_id', $categoryId)
            ->where('is_active', true)
            ->orderByRaw('coalesce(sort_order, 9999)')
            ->get();
    }

    public function parseFilters(array $query): array
    {
        $categoryIds = $query['category_id'] ?? $query['categoryIds'] ?? null;

        if (is_string($categoryIds)) {
            $categoryIds = array_filter(array_map('intval', explode(',', $categoryIds)));
        }

        if (is_numeric($categoryIds)) {
            $categoryIds = [(int) $categoryIds];
        }

        return [
            'page' => max((int) ($query['page'] ?? 0), 0),
            'size' => min(max((int) ($query['size'] ?? 20), 1), 100),
            'category_ids' => is_array($categoryIds) ? array_map('intval', $categoryIds) : null,
            'min_price' => $query['min_price'] ?? $query['minPrice'] ?? null,
            'max_price' => $query['max_price'] ?? $query['maxPrice'] ?? null,
            'in_stock' => $query['in_stock'] ?? $query['inStock'] ?? null,
            'search' => $query['search'] ?? null,
            'sort_by' => $query['sort_by'] ?? $query['sortBy'] ?? null,
            'sort_direction' => strtolower($query['sort_direction'] ?? $query['sortDirection'] ?? 'asc') === 'desc' ? 'desc' : 'asc',
            'attributes' => $this->parseAttributeFilters($query),
        ];
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if ($filters['category_ids']) {
            $query->whereIn('category_id', $filters['category_ids']);
        }

        if ($filters['min_price'] !== null) {
            $query->where('price', '>=', $filters['min_price']);
        }

        if ($filters['max_price'] !== null) {
            $query->where('price', '<=', $filters['max_price']);
        }

        if (filter_var($filters['in_stock'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true) {
            $query->where('quantity', '>', 0);
        }

        if ($filters['search']) {
            $this->applySearch($query, $filters['search']);
        }

        foreach ($filters['attributes']['equals'] as $key => $values) {
            $query->where(function ($builder) use ($key, $values) {
                foreach ($values as $value) {
                    $builder->orWhereRaw('attributes ->> ? = ?', [$key, $value]);
                }
            });
        }

        foreach ($filters['attributes']['min'] as $key => $value) {
            $query->whereRaw('CAST(attributes ->> ? AS NUMERIC) >= ?', [$key, $value]);
        }

        foreach ($filters['attributes']['max'] as $key => $value) {
            $query->whereRaw('CAST(attributes ->> ? AS NUMERIC) <= ?', [$key, $value]);
        }
    }

    private function applySearch(Builder $query, string $search): void
    {
        $keyword = "%{$search}%";
        $query->where(function ($builder) use ($keyword) {
            $builder->where('name', 'ilike', $keyword)->orWhere('description', 'ilike', $keyword);
        });
    }

    private function applySorting(Builder $query, array $filters): void
    {
        $allowed = ['price', 'name', 'created_at'];
        $sortBy = in_array($filters['sort_by'], $allowed, true) ? $filters['sort_by'] : 'created_at';
        $direction = $filters['sort_by'] ? $filters['sort_direction'] : 'desc';

        $query->orderBy($sortBy, $direction);
    }

    private function parseAttributeFilters(array $query): array
    {
        $result = ['equals' => [], 'min' => [], 'max' => []];

        foreach ($query as $key => $value) {
            if (!str_starts_with($key, 'attr.')) {
                continue;
            }

            $attributeKey = substr($key, 5);
            $target = 'equals';

            if (str_ends_with($attributeKey, '_min')) {
                $attributeKey = substr($attributeKey, 0, -4);
                $target = 'min';
            } elseif (str_ends_with($attributeKey, '_max')) {
                $attributeKey = substr($attributeKey, 0, -4);
                $target = 'max';
            }

            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $attributeKey)) {
                continue;
            }

            if ($target === 'equals') {
                $result[$target][$attributeKey] = is_array($value) ? $value : explode(',', (string) $value);
            } else {
                $result[$target][$attributeKey] = $value;
            }
        }

        return $result;
    }
}
