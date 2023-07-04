<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function salesOrderItems(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class, 'sales_order_detail_id');
    }

    public function histories(): MorphMany
    {
        return $this->morphMany(StockHistory::class, 'model');
    }
}
