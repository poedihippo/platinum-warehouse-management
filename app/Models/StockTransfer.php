<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockTransfer extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->user_id)) {
                $model->user_id = auth('sanctum')->id();
            }
        });
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function fromStockProductUnit(): BelongsTo
    {
        return $this->belongsTo(StockProductUnit::class, 'from_stock_product_unit_id');
    }

    public function toStockProductUnit(): BelongsTo
    {
        return $this->belongsTo(StockProductUnit::class, 'to_stock_product_unit_id');
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
