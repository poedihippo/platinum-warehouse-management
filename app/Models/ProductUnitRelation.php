<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductUnitRelation extends Model
{
    protected $fillable = [
        'product_unit_id',
        'related_product_unit_id',
        'qty',
    ];

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class);
    }

    public function relatedProductUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'related_product_unit_id');
    }
}
