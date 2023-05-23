<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesOrderDetail extends Model
{
    protected $guarded = [];

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
