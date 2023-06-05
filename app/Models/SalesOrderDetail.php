<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrderDetail extends Model
{
    protected $guarded = [];

    protected $casts = [
        'qty' => 'integer',
        'fulfilled_qty' => 'integer',
    ];

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function productUnit()
    {
        return $this->belongsTo(ProductUnit::class);
    }

    public function salesOrderItems()
    {
        return $this->hasMany(SalesOrderItem::class, 'sales_order_detail_id');
    }
}
