<?php

namespace App\Models;

use App\Events\Stocks\StockOpnameDetailCreated;
use Illuminate\Database\Eloquent\Model;

class StockOpnameDetail extends Model
{
    protected $guarded = [];
    protected $casts = [
        'qty' => 'integer',
        'adjust_qty' => 'integer',
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

    public function stockOpnameItems()
    {
        return $this->hasMany(StockOpnameItem::class);
    }

    public function stockOpname()
    {
        return $this->belongsTo(StockOpname::class);
    }

    public function stockProductUnit()
    {
        return $this->belongsTo(StockProductUnit::class);
    }
}
