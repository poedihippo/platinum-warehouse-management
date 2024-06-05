<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdjustmentRequest extends Model
{
    use SoftDeletes;

    protected $guarded = [];
    protected $casts = [
        'is_increment' => 'boolean',
        // 'is_approved' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->user_id)) $model->user_id = auth('sanctum')->id();
        });
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function stockProductUnit(): BelongsTo
    {
        return $this->belongsTo(StockProductUnit::class);
    }

    public function histories(): MorphMany
    {
        return $this->morphMany(StockHistory::class, 'model');
    }

    public function scopeStartDate(Builder $query, $value = null)
    {
        $value = is_null($value) ? date('Y-m-d') : date('Y-m-d', strtotime($value));
        return $query->whereDate('created_at', '>=', $value);
    }

    public function scopeEndDate(Builder $query, $value = null)
    {
        $value = is_null($value) ? date('Y-m-d') : date('Y-m-d', strtotime($value));
        return $query->whereDate('created_at', '<=', $value);
    }
}
