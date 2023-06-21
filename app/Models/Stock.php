<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
// use Spatie\MediaLibrary\InteractsWithMedia;

class Stock extends Model
{
    use HasUlids, SoftDeletes;
    protected $guarded = [];
    protected $casts = [
        'scanned_count' => 'integer'
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function stockProductUnit()
    {
        return $this->belongsTo(StockProductUnit::class);
    }

    public function childs()
    {
        return $this->hasMany(self::class, 'parent_id', 'id');
    }

    // public function productUnit()
    // {
    //     return $this->belongsTo(ProductUnit::class);
    // }

    // public function warehouse()
    // {
    //     return $this->belongsTo(Warehouse::class);
    // }

    public function receiveOrder()
    {
        return $this->belongsTo(ReceiveOrder::class);
    }

    public function receiveOrderDetail()
    {
        return $this->belongsTo(ReceiveOrderDetail::class);
    }

    protected function qrCode(): Attribute
    {
        return Attribute::make(
            get: function (string $value) {
                if (is_null($value) || $value == '') return null;
                if (config('app.env') === 'production') return Storage::temporaryUrl($value, now()->addMinutes(5));

                return url(Storage::url($value));
            },
        );
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
