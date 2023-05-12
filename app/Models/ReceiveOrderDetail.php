<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReceiveOrderDetail extends Model
{
    protected $guarded = [];

    public function receiveOrder()
    {
        $this->belongsTo(ReceiveOrder::class);
    }
}
