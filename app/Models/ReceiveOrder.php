<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReceiveOrder extends Model
{
    protected $guarded = [];

    public function details()
    {
        return $this->hasMany(ReceiveOrderDetail::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
