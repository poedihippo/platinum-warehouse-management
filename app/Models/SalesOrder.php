<?php

namespace App\Models;

use App\Enums\SalesOrderStatus;
use Illuminate\Database\Eloquent\Model;

class SalesOrder extends Model
{
    protected $fillable = [
        'user_id',
        'reseller_id',
        'code',
        'transaction_date',
        'status',
    ];

    protected $casts = [
        'status' => SalesOrderStatus::class,
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->user_id = user()->id;
            $model->status = SalesOrderStatus::PENDING;
        });
    }

    public function details()
    {
        return $this->hasMany(SalesOrderDetail::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reseller()
    {
        return $this->belongsTo(User::class, 'reseller_id');
    }
}
