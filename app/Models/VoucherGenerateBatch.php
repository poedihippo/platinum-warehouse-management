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
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'source' => BatchSource::class,
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    protected static function booted()
    {
        static::saving(function ($model) {
            $model->user_id = auth('sanctum')->id();
        });

        static::updated(function ($model) {
            if ($model->isDirty('start_date') || $model->isDirty('end_date')) {
                $model->vouchers()->update([
                    'start_date' => $model->start_date,
                    'end_date' => $model->end_date,
                ]);
            }
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
