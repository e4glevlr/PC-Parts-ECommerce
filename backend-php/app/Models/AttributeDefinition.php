<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttributeDefinition extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'code',
        'display_name',
        'data_type',
        'input_type',
        'unit',
        'sort_order',
        'options',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'options' => 'array',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
