<?php

namespace App\Models;

use App\Events\Stocks\StockOpnameDetailCreated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class StockOpnameDetail extends Model
{
    protected $guarded = [];
    protected $casts = [
        'qty' => 'integer',
        'scanned_qty' => 'integer',
        'is_done' => 'boolean',
    ];

    protected static function booted()
    {
        static::created(function ($model) {
            StockOpnameDetailCreated::dispatch($model);
        });

        static::saved(function ($model) {
            if ($model->isDirty('is_done')) {
                $model->done_at = now();
            }
        });
    }

    public function stockOpnameItems(): HasMany
    {
        return $this->hasMany(StockOpnameItem::class);
    }

    public function stockOpname(): BelongsTo
    {
        return $this->belongsTo(StockOpname::class);
    }

    public function stockProductUnit(): BelongsTo
    {
        return $this->belongsTo(StockProductUnit::class);
    }

    public function histories(): MorphMany
    {
        return $this->morphMany(StockHistory::class, 'model');
    }
}
