<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryOrderDetail extends Model
{
    protected $guarded = [];

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function salesOrderDetail(): BelongsTo
    {
        return $this->belongsTo(SalesOrderDetail::class);
    }
}
