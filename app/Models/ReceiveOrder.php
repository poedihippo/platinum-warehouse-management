<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReceiveOrder extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_complete' => 'boolean'
    ];

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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function refreshStatus(): void
    {
        if ($this->details->every(fn ($detail) => $detail->is_verified === 1) === true) $this->update(['is_complete' => 1]);
        $this->update(['is_complete' => 0]);
    }
}
