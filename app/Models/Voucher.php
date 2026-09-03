<?php

namespace App\Models;

use App\Traits\CustomSoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

class Voucher extends Model
{
    use CustomSoftDeletes;

    protected $fillable = [
        'voucher_generate_batch_id',
        'voucher_category_id',
        'code',
        'description',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function voucherGenerateBatch(): BelongsTo
    {
        return $this->belongsTo(VoucherGenerateBatch::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(VoucherCategory::class, 'voucher_category_id');
    }

    public function salesOrders(): BelongsToMany
    {
        return $this->belongsToMany(SalesOrder::class)->withTimestamps();
    }

    public function getIsUsedAttribute(): bool
    {
        return $this->salesOrders()->exists();
    }

    public function isValid(): bool
    {
        $today = Carbon::today();

        if ($this->start_date && $today->lt($this->start_date->startOfDay())) {
            return false;
        }

        if ($this->end_date && $today->gt($this->end_date->startOfDay())) {
            return false;
        }

        return true;
    }

    public function scopeValidNow(Builder $query): Builder
    {
        $today = Carbon::today();

        return $query->where(function ($q) use ($today) {
            $q->whereNull('start_date')
                ->orWhere('start_date', '<=', $today);
        })->where(function ($q) use ($today) {
            $q->whereNull('end_date')
                ->orWhere('end_date', '>=', $today);
        });
    }
}
