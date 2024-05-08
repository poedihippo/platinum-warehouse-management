<?php

namespace App\Models;

use App\Events\UnverifiedROEvent;
use App\Events\VerifiedROEvent;
use App\Traits\FilterStartEndDate;
use App\Traits\Tenanted;
use Illuminate\Database\Eloquent\Model;

class ReceiveOrder extends Model
{
    use FilterStartEndDate, Tenanted;
    protected $guarded = [];

    protected $casts = [
        'is_done' => 'boolean'
    ];

    protected static function booted()
    {
        static::updated(function ($model) {
            if ($model->isDirty('is_done')) {
                if ($model->is_done === true) {
                    VerifiedROEvent::dispatch($model);
                } else {
                    UnverifiedROEvent::dispatch($model);
                }
            }
        });

        static::deleting(function ($model) {
            UnverifiedROEvent::dispatch($model);
        });
    }

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
        if ($this->details->every(fn ($detail) => $detail->is_verified === true) === true) {
            $this->update(['is_done' => 1]);
        } else {
            $this->update(['is_done' => 0]);
        }
    }
}
