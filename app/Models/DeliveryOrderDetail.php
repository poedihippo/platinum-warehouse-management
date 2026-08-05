<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryOrderDetail extends Model
{
    protected $fillable = [
        'delivery_order_id',
        'sales_order_detail_id',
        'qty',
    ];

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function salesOrderDetail(): BelongsTo
    {
        return $this->belongsTo(SalesOrderDetail::class);
    }

    public function salesOrderItems(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class, 'delivery_order_detail_id');
    }
}
