<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockProductUnit extends Model
{
    use SoftDeletes;
    protected $guarded = [];
    protected $casts = [
        'qty' => 'integer',
    ];

    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }

    public function productUnit()
    {
        return $this->belongsTo(ProductUnit::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function scopeWhereProductBrandId(Builder $query, $id)
    {
        return $query->whereHas('productUnit.product', fn ($q) => $q->where('product_brand_id', $id));
    }

    public function scopeWhereProductCategoryId(Builder $query, $id)
    {
        return $query->whereHas('productUnit.product', fn ($q) => $q->where('product_category_id', $id));
    }

    public function scopeProductUnit(Builder $query, $value)
    {
        return $query->whereHas('productUnit', fn ($q) => $q->where('name', 'like', '%' . $value . '%')->orWhere('code', 'like', '%' . $value . '%'));
    }
}
