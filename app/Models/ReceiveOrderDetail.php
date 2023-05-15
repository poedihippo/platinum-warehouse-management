<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReceiveOrderDetail extends Model
{
    protected $guarded = [];
    protected $casts = [
        'qty' => 'integer',
        'bruto_unit_price' => 'integer',
        'adjust_qty' => 'integer',
        'is_package' => 'boolean',
        'is_verified' => 'boolean',
    ];

    protected static function booted()
    {
        static::updated(function ($model) {
            // dd($model);
        });
    }

    public function receiveOrder()
    {
        return $this->belongsTo(ReceiveOrder::class);
    }

    public function productUnit()
    {
        return $this->belongsTo(ProductUnit::class);
    }

    public function uom()
    {
        return $this->belongsTo(Uom::class);
    }
}
