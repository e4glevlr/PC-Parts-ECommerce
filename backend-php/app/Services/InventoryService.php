<?php

namespace App\Services;

use App\Exceptions\BadRequestException;
use App\Exceptions\ResourceNotFoundException;
use App\Models\InventoryLog;
use App\Models\Product;

class InventoryService
{
    public function products(int $page, int $size, ?string $status, ?string $search): array
    {
        $query = Product::with('category');

        if ($status === 'low_stock') {
            $query->where('quantity', '>', 0)->whereColumn('quantity', '<=', 'low_stock_threshold');
        } elseif ($status === 'out_of_stock') {
            $query->where('quantity', 0);
        } elseif ($status === 'in_stock') {
            $query->where('quantity', '>', 0);
        }

        if ($search) {
            $keyword = "%{$search}%";
            $query->where('name', 'ilike', $keyword);
        }

        $total = $query->count();
        $items = $query->orderByDesc('updated_at')->offset($page * $size)->limit($size)->get();

        return [$items, $total];
    }

    public function product(int $productId): Product
    {
        $product = Product::with('category')->find($productId);

        if (!$product) {
            throw new ResourceNotFoundException('Sản phẩm', 'id', $productId);
        }

        return $product;
    }

    public function lowStock(int $threshold): array
    {
        $products = Product::with('category')->where('quantity', '>', 0)->where('quantity', '<=', $threshold)->get();

        return [
            'total_products' => Product::count(),
            'low_stock_products' => $products->count(),
            'out_of_stock_products' => Product::where('quantity', 0)->count(),
            'products' => $products,
        ];
    }

    public function outOfStock()
    {
        return Product::with('category')->where('quantity', 0)->get();
    }

    public function needRestock()
    {
        return Product::with('category')->whereColumn('quantity', '<=', 'low_stock_threshold')->get();
    }

    public function adjust(int $productId, string $changeType, int $quantity, string $reason, int $performedBy): Product
    {
        $product = $this->product($productId);
        $newQuantity = $changeType === 'IN' ? $product->quantity + $quantity : $product->quantity - $quantity;

        if ($newQuantity < 0) {
            throw new BadRequestException('Không đủ hàng trong kho');
        }

        $product->update(['quantity' => $newQuantity]);
        InventoryLog::create([
            'product_id' => $productId,
            'change_type' => $changeType,
            'quantity_change' => $quantity,
            'reason' => $reason,
            'performed_by' => $performedBy,
            'created_at' => now(),
        ]);

        return $product->refresh()->load('category');
    }

    public function threshold(int $productId, int $threshold): Product
    {
        $product = $this->product($productId);
        $product->update(['low_stock_threshold' => $threshold]);

        return $product->refresh()->load('category');
    }

    public function logs(int $page, int $size, ?string $changeType, ?string $dateFrom, ?string $dateTo, ?string $search): array
    {
        $query = InventoryLog::with(['product', 'performer'])->orderByDesc('created_at');

        if ($changeType) {
            $query->where('change_type', $changeType);
        }

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if ($search) {
            $query->whereHas('product', fn ($builder) => $builder->where('name', 'ilike', "%{$search}%"));
        }

        $total = $query->count();
        $items = $query->offset($page * $size)->limit($size)->get();

        return [$items, $total];
    }

    public function productLogs(int $productId, int $page, int $size): array
    {
        $query = InventoryLog::with(['product', 'performer'])->where('product_id', $productId)->orderByDesc('created_at');
        $total = $query->count();
        $items = $query->offset($page * $size)->limit($size)->get();

        return [$items, $total];
    }
}
