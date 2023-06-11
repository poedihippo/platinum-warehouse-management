<?php

namespace App\Models;

use App\Events\ProductUnits\ProductUnitCreated;
use Illuminate\Database\Eloquent\Model;

class ProductUnit extends Model
{
    protected $guarded = [];

    protected static function booted()
    {
        static::created(function ($model) {
            ProductUnitCreated::dispatch($model);
        });
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function uom()
    {
        return $this->belongsTo(Uom::class);
    }

    public function stockProductUnits()
    {
        return $this->hasMany(StockProductUnit::class);
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }
}
