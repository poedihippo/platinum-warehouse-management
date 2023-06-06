<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductUnitBlacklist extends Model
{
    protected $guarded = [];

    public function productUnit()
    {
        return $this->belongsTo(ProductUnit::class);
    }
}
