<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AdjustmentRequest extends Model
{
    protected $guarded = [];
    protected $casts = [
        'is_increment' => 'boolean',
        'is_approved' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->user_id = auth()->user()->id;
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function stockProductUnit()
    {
        return $this->belongsTo(StockProductUnit::class);
    }

    public function scopeStartDate(Builder $query, $value = null)
    {
        $value = is_null($value) ? date('Y-m-d') : date('Y-m-d', strtotime($value));
        return $query->whereDate('created_at', '>=', $value);
    }

    public function scopeEndDate(Builder $query, $value = null)
    {
        $value = is_null($value) ? date('Y-m-d') : date('Y-m-d', strtotime($value));
        return $query->whereDate('created_at', '<=', $value);
    }
}
