<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryOrder extends Model
{
    protected $fillable = [
        'sales_order_id',
        'invoice_no',
        'code',
        'description',
    ];

    protected static function booted()
    {
        // static::creating(function ($model) {
        //     $model->user_id = auth()->user()->id;
        //     $model->status = SalesOrderStatus::PENDING;
        // });
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }
}
