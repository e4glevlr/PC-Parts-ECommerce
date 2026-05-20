<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'quantity',
        'low_stock_threshold',
        'category_id',
        'specifications',
        'attributes',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer',
        'low_stock_threshold' => 'integer',
        'specifications' => 'array',
        'attributes' => 'array',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['is_low_stock', 'primary_image_url'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(InventoryLog::class);
    }

    protected function isLowStock(): Attribute
    {
        return Attribute::get(fn () => $this->quantity <= $this->low_stock_threshold);
    }

    protected function primaryImageUrl(): Attribute
    {
        return Attribute::get(function () {
            $images = $this->relationLoaded('images') ? $this->images : $this->images()->get();
            $primary = $images->firstWhere('is_primary', true) ?: $images->first();
            return $primary ? $primary->image_url : 'https://cdn.image.com/example.jpg';
        });
    }
}
