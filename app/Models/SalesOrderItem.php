<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrderItem extends Model
{
    protected $guarded = [];

    // protected static function booted()
    // {
    //     static::deleted(function ($model) {

    //     });
    // }

    public function stock()
    {
        return $this->belongsTo(Stock::class);
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function salesOrderDetail()
    {
        return $this->belongsTo(SalesOrderDetail::class);
    }
}
