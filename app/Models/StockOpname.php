<?php

namespace App\Models;

use App\Events\Stocks\StockOpnameCreated;
use App\Traits\Tenanted;
use Illuminate\Database\Eloquent\Model;

class StockOpname extends Model
{
    use Tenanted;
    protected $guarded = [];
    protected $casts = [
        'is_done' => 'boolean'
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->user_id = auth('sanctum')->id();
        });

        static::created(function ($model) {
            StockOpnameCreated::dispatch($model);
        });

        static::saved(function ($model) {
            if ($model->isDirty('is_done')) {
                $model->done_at = now();

                if ($model->is_done) {
                    $model->details?->each(function ($stockOpnameDetail) use ($model) {
                        $stockOpnameItems = $stockOpnameDetail->stockOpnameItems()->where('is_scanned', 0)->get() ?? collect([]);
                        $stockOpnameItems?->each(function ($stockOpnameItem) {
                            // if (!$stockOpnameItem->is_scanned) {
                            Stock::find($stockOpnameItem->stock_id)?->delete();
                            // }
                        });

                        $stockOpnameDetail->histories()->create([
                            'user_id' => auth('sanctum')->id(),
                            'stock_product_unit_id' => $stockOpnameDetail->stock_product_unit_id,
                            'value' => $stockOpnameItems?->count() ?? 0,
                            'is_increment' => 0,
                            'description' => 'Stock Opname - ' . $model->description,
                            'ip' => request()->ip(),
                            'agent' => request()->header('user-agent'),
                        ]);
                    });
                } else {
                    $model->details?->each(function ($stockOpnameDetail) use ($model) {
                        $stockOpnameItems = $stockOpnameDetail->stockOpnameItems()->where('is_scanned', 0)->get() ?? collect([]);
                        $stockOpnameItems?->each(function ($stockOpnameItem) {
                            // if (!$stockOpnameItem->is_scanned) {
                            Stock::withTrashed()->find($stockOpnameItem->stock_id)?->restore();
                            // }
                        });

                        $stockOpnameDetail->histories()->create([
                            'user_id' => auth('sanctum')->id(),
                            'stock_product_unit_id' => $stockOpnameDetail->stock_product_unit_id,
                            'value' => $stockOpnameItems?->count() ?? 0,
                            'is_increment' => 1,
                            'description' => 'Stock Opname - ' . $model->description,
                            'ip' => request()->ip(),
                            'agent' => request()->header('user-agent'),
                        ]);
                    });
                }
            }
        });
    }

    public function details()
    {
        return $this->hasMany(StockOpnameDetail::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
