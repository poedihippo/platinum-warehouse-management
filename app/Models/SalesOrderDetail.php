<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class SalesOrderDetail extends Model
{
    protected $guarded = [];

    protected $casts = [
        'qty' => 'integer',
        'fulfilled_qty' => 'integer',
        'unit_price' => 'integer',
        'discount' => 'integer',
        'tax' => 'integer',
        'total_price' => 'integer',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class);
    }

    public function packaging(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'packaging_id');
    }

    public function salesOrderItems(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class, 'sales_order_detail_id');
    }

    // kalo ada fitur quantity di DO detail, harus pake hasMany.
    // sementara pake hasOne dulu karena belum handle qty nya. yg penting SO detail masuk ke DO detail
    // ubah validasi DeliveryOrderController@attach()
    // public function deliveryOrderDetails(): HasMany
    // {
    //     return $this->hasMany(DeliveryOrderDetail::class);
    // }

    public function deliveryOrderDetail(): HasOne
    {
        return $this->hasOne(DeliveryOrderDetail::class);
    }

    public function histories(): MorphMany
    {
        return $this->morphMany(StockHistory::class, 'model');
    }

    public function scopeHasDeliveryOrder(Builder $query, bool $value = true)
    {
        if ($value) return $query->has('deliveryOrderDetail');
        return $query->doesntHave('deliveryOrderDetail');
    }
}
