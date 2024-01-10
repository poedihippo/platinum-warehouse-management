<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// use Spatie\MediaLibrary\InteractsWithMedia;

class Stock extends Model
{
    use HasUlids, SoftDeletes;
    protected $guarded = [];
    protected $casts = [
        'scanned_count' => 'integer',
        'is_tempel' => 'boolean'
    ];

    public function parent() : BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function stockProductUnit() : BelongsTo
    {
        return $this->belongsTo(StockProductUnit::class);
    }


    public function childs() : HasMany
    {
        return $this->hasMany(self::class, 'parent_id', 'id');
    }

    public function salesOrderItems() : HasMany
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function receiveOrder() : BelongsTo
    {
        return $this->belongsTo(ReceiveOrder::class);
    }

    public function receiveOrderDetail() : BelongsTo
    {
        return $this->belongsTo(ReceiveOrderDetail::class);
    }

    protected function qrCode() : Attribute
    {
        return Attribute::make(
            get: function (string $value) {
                if (is_null($value) || $value == '') return null;
                if (config('app.env') === 'production') return Storage::temporaryUrl($value, now()->addMinutes(5));

                return url(Storage::url($value));
            },
        );
    }

    public function scopeWhereAvailableStock(Builder $query)
    {
        return $query->doesntHave('salesOrderItems');
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

    public function scopeIsShowGroup(Builder $query, $value = 0)
    {
        if ($value == 0) return $query->doesntHave('childs');
        return $query->has('childs');
    }
}
