<?php

namespace App\Models;

use App\Traits\CustomSoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Voucher extends Model
{
    use CustomSoftDeletes;

    protected $fillable = [
        'voucher_generate_batch_id',
        'voucher_category_id',
        'code',
        'description',
    ];

    // protected $appends = ['is_used'];

    public function voucherGenerateBatch(): BelongsTo
    {
        return $this->belongsTo(VoucherGenerateBatch::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(VoucherCategory::class, 'voucher_category_id');
    }

    public function salesOrder(): HasOne
    {
        return $this->hasOne(SalesOrder::class);
    }

    public function getIsUsedAttribute(): bool
    {
        $this->load(['salesOrder' => fn ($q) => $q->select('id', 'voucher_id')]);
        return (bool) $this->salesOrder;
    }
}
