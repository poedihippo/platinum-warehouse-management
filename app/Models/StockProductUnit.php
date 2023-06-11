<?php

namespace App\Models;

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
}
