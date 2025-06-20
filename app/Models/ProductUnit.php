<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Events\ProductUnits\ProductUnitCreated;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductUnit extends Model
{
    use SoftDeletes;

    protected $guarded = [];
    protected $casts = [
        'is_generate_qr' => 'boolean',
        'is_auto_tempel' => 'boolean',
        'is_auto_stock' => 'boolean',
    ];

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

    public function packaging()
    {
        return $this->belongsTo(self::class, 'packaging_id');
    }

    public function uom()
    {
        return $this->belongsTo(Uom::class);
    }

    public function stockProductUnits()
    {
        return $this->hasMany(StockProductUnit::class);
    }

    public function scopeWhereProductBrandId(Builder $query, $id)
    {
        return $query->whereHas('product', fn($q) => $q->where('product_brand_id', $id));
    }

    public function scopeWhereProductCategoryId(Builder $query, $id)
    {
        return $query->whereHas('product', fn($q) => $q->where('product_category_id', $id));
    }
}
