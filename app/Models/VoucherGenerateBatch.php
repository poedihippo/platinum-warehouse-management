<?php

namespace App\Models;

use App\Enums\BatchSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VoucherGenerateBatch extends Model
{
    protected $fillable = [
        'user_id',
        'source',
        'description',
    ];

    protected $casts = [
        'source' => BatchSource::class,
    ];

    protected static function booted()
    {
        static::saving(function ($model) {
            $model->user_id = auth()->id();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }
}
