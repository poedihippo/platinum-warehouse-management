<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $guarded = [];

    public function receiveOrders()
    {
        return $this->hasMany(ReceiveOrder::class);
    }
}
