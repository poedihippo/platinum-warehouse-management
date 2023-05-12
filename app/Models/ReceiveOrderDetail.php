<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReceiveOrderDetail extends Model
{
    protected $guarded = [];
    protected $casts = [
        'is_package' => 'boolean'
    ];

    public function receiveOrder()
    {
        return $this->belongsTo(ReceiveOrder::class);
    }

    public function productUnit()
    {
        return $this->belongsTo(ProductUnit::class);
    }
}
