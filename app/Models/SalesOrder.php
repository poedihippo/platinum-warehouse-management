<?php

namespace App\Models;

use App\Enums\SettingEnum;
use App\Traits\FilterStartEndDate;
use App\Traits\Tenanted;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrder extends Model
{
    use SoftDeletes, FilterStartEndDate, Tenanted;

    public ?int $expected_price = null;

    protected $appends = [
        'additional_discount_percentage',
    ];

    protected $hidden = [
        'raw_source',
        'records',
    ];

    protected $fillable = [
        'user_id',
        'voucher_id',
        'reseller_id',
        'warehouse_id',
        'invoice_no',
        'raw_source',
        'records',
        'transaction_date',
        'shipment_estimation_datetime',
        'shipment_fee',
        'additional_discount',
        'price',
        'description',
        'is_invoice',
    ];

    protected $casts = [
        'raw_source' => 'array',
        'records' => 'array',
        'shipment_fee' => 'integer',
        'additional_discount' => 'integer',
        'price' => 'integer',
        'is_invoice' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->user_id = auth()->id();
            if (empty($model->description))
                $model->description = '#Barang yang sudah dibeli tidak dapat dikembalikan. Terimakasih';
        });

        static::created(function ($model) {
            if (empty($model->invoice_no)) {
                $model->invoice_no = self::getSoNumber();
                $model->save();
            }
        });
    }

    public function getAdditionalDiscountPercentageAttribute()
    {
        return $this->raw_source['additional_discount'] ?? 0;
    }

    // public function deliveryOrder()
    // {
    //     return $this->hasOne(DeliveryOrder::class);
    // }

    public function details(): HasMany
    {
        return $this->hasMany(SalesOrderDetail::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reseller_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public static function getSoNumber(): string
    {
        $key = SettingEnum::SO_NUMBER;
        return DB::transaction(function () use ($key) {
            // Get current value to use. We use lock for update
            // to prevent other thread to read this row until we update it
            $lastSoNumber = DB::table('settings')
                ->where('key', $key)
                ->lockForUpdate()
                ->first('value')?->value ?? null;

            if (isset($lastSoNumber) && !is_null($lastSoNumber) && $lastSoNumber != '') {
                $arrayLastSoNumber = explode('/', $lastSoNumber);

                if (is_array($arrayLastSoNumber) && count($arrayLastSoNumber) == 5 && date('m') == $arrayLastSoNumber[2] && date('y') == $arrayLastSoNumber[3]) {
                    $arrayLastSoNumber[4] = sprintf('%02d', (int) $arrayLastSoNumber[4] + 1);
                    $nextLastSoNumber = implode('/', $arrayLastSoNumber);
                } else {
                    $lastSoNumber = sprintf('PAS/SO/%s/%s/01', date('m'), date('y'));
                    $nextLastSoNumber = sprintf('PAS/SO/%s/%s/02', date('m'), date('y'));
                }
            } else {
                $lastSoNumber = sprintf('PAS/SO/%s/%s/01', date('m'), date('y'));
                $nextLastSoNumber = sprintf('PAS/SO/%s/%s/02', date('m'), date('y'));
            }

            // update the value with $nextLastSoNumber
            DB::table('settings')
                ->where('key', $key)
                ->update(['value' => trim($nextLastSoNumber)]);

            return trim($lastSoNumber);
        });
    }

    public function scopeDetailsHasDO(Builder $query, bool $value = true)
    {
        // if ($value) return $query->whereHas('details', fn ($q) => $q->has('deliveryOrderDetail'));
        // return $query->whereHas('details', fn ($q) => $q->doesntHave('deliveryOrderDetail'));
        return $query->whereHas('details', fn ($q) => $q->hasDeliveryOrder((bool)$value));
    }
}
