<?php

namespace App\Models;

use App\Enums\SalesOrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesOrder extends Model
{
    protected $casts = [
        'status' => SalesOrderStatus::class,
    ];

    protected $guarded = [];

    public function details()
    {
        return $this->hasMany(SalesOrderDetail::class);
    }

    public function User()
    {
        return $this->belongsTo(User::class);
    }

    public function Reseller()
    {
        return $this->belongsTo(User::class, 'reseller_id');
    }
}
