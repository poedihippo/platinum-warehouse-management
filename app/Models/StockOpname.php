<?php

namespace App\Models;

use App\Events\Stocks\StockOpnameCreated;
use Illuminate\Database\Eloquent\Model;

class StockOpname extends Model
{
    protected $guarded = [];
    protected $casts = [
        'is_done' => 'boolean'
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->user_id = auth()->user()->id;
        });

        static::created(function ($model) {
            StockOpnameCreated::dispatch($model);
        });

        static::saved(function ($model) {
            if ($model->isDirty('is_done')) {
                $model->done_at = now();

                if ($model->is_done) {
                    $model->details?->each(function ($stockOpnameDetail) {
                        $stockOpnameDetail->stockOpnameItems()->where('is_scanned', 0)->get()?->each(function ($stockOpnameItem) {
                            // if (!$stockOpnameItem->is_scanned) {
                            Stock::find($stockOpnameItem->stock_id)?->delete();
                            // }
                        });
                    });
                } else {
                    $model->details?->each(function ($stockOpnameDetail) {
                        $stockOpnameDetail->stockOpnameItems()->where('is_scanned', 0)->get()?->each(function ($stockOpnameItem) {
                            // if (!$stockOpnameItem->is_scanned) {
                            Stock::withTrashed()->find($stockOpnameItem->stock_id)?->restore();
                            // }
                        });
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
