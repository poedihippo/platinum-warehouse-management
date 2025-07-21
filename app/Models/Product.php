<?php

namespace App\Models;

use App\Enums\CompanyEnum;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'product_category_id',
        'product_brand_id',
        'company',
        'name',
        'description',
    ];

    protected $casts = [
        'company' => CompanyEnum::class,
    ];

    public function productCategory()
    {
        return $this->belongsTo(ProductCategory::class);
    }

    public function productBrand()
    {
        return $this->belongsTo(ProductBrand::class);
    }
}
