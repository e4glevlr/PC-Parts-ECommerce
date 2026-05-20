<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Token extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'token',
        'token_type',
        'expiration_date',
        'revoked',
        'expired',
        'user_id',
    ];

    protected $casts = [
        'expiration_date' => 'datetime',
        'revoked' => 'boolean',
        'expired' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
