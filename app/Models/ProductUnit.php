<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Events\ProductUnits\ProductUnitCreated;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductUnit extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'refer_id',
        // 'packaging_id',
        'uom_id',
        'name',
        'price',
        'refer_qty',
        // 'description',
        'code',
        'is_generate_qr',
        'is_ppn',
        // 'is_auto_tempel',
        'is_auto_stock',
    ];

    protected $casts = [
        'is_generate_qr' => 'boolean',
        // 'is_auto_tempel' => 'boolean',
        'is_auto_stock' => 'boolean',
    ];

    protected static function booted()
    {
        static::created(function ($model) {
            ProductUnitCreated::dispatchIf(is_null($model->refer_id), $model);
        });

        static::deleting(function ($model) {
            $model->code = $model->code . '-deleted';
        });
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // public function packaging()
    // {
    //     return $this->belongsTo(self::class, 'packaging_id');
    // }

    public function refer()
    {
        return $this->belongsTo(self::class, 'refer_id');
    }

    public function uom()
    {
        return $this->belongsTo(Uom::class);
    }

    public function relations()
    {
        return $this->hasMany(ProductUnitRelation::class);
    }

    public function stockProductUnits()
    {
        return $this->hasMany(StockProductUnit::class);
    }

    public function stockProductUnit()
    {
        return $this->hasOne(StockProductUnit::class);
    }


    public function scopeSearch(Builder $query, string $value)
    {
        return $query->where(fn($q) => $q->where('name', 'like', "%$value%")->orWhere('code', 'like', "%$value%"));
    }

    public function scopeWhereProductBrandId(Builder $query, $id)
    {
        return $query->whereHas('product', fn($q) => $q->where('product_brand_id', $id));
    }

    public function scopeWhereProductCategoryId(Builder $query, $id)
    {
        return $query->whereHas('product', fn($q) => $q->where('product_category_id', $id));
    }

    public function scopeWhereCompany(Builder $query, string $company)
    {
        return $query->whereHas('product', fn($q) => $q->where('company', $company));
    }
}
