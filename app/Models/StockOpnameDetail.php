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
    ];

    protected static function booted()
    {
        static::created(function ($model) {
            StockOpnameDetailCreated::dispatch($model);
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
