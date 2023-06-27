<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ReceiveOrderDetail extends Model
{
    protected $guarded = [];
    protected $casts = [
        'qty' => 'integer',
        'bruto_unit_price' => 'integer',
        'adjust_qty' => 'integer',
        'is_verified' => 'boolean',
    ];

    public function receiveOrder()
    {
        return $this->belongsTo(ReceiveOrder::class);
    }

    public function productUnit()
    {
        return $this->belongsTo(ProductUnit::class);
    }

    // public function uom()
    // {
    //     return $this->belongsTo(Uom::class);
    // }

    public function stocks()
    {
        return $this->hasMany(Stock::class, 'receive_order_detail_id');
    }

    public function scopeProductUnit(Builder $query, $value)
    {
        return $query->whereHas('productUnit', fn ($q) => $q->where('name', 'like', '%' . $value . '%')->orWhere('code', 'like', '%' . $value . '%'));
    }
}
