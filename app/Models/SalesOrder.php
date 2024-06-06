<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Enums\SalesOrderType;
use App\Enums\SettingEnum;
use App\Enums\UserType;
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
        'auto_discount_nominal',

        'voucher_code',
        'voucher_type',
        'voucher_value',
        'voucher_value_nominal',

        'payment_amount',
        'payment_status',
    ];

    protected $hidden = [
        'raw_source',
        'records',
    ];

    protected $fillable = [
        'user_id',
        'voucher_id',
        'reseller_id',
        'spg_id',
        'warehouse_id',
        'invoice_no',
        'raw_source',
        'records',
        'transaction_date',
        'shipment_estimation_datetime',
        'shipment_fee',
        'additional_discount',
        'auto_discount',
        'price',
        'description',
        'is_invoice',
        'type',
    ];

    protected $casts = [
        'raw_source' => 'array',
        'records' => 'array',
        'shipment_fee' => 'integer',
        'additional_discount' => 'integer',
        'auto_discount' => 'float',
        'price' => 'integer',
        'is_invoice' => 'boolean',
        'type' => SalesOrderType::class
    ];

    protected static function booted()
    {
        static::saving(function ($model) {
            $model->user_id = auth('sanctum')->id();
            if (empty($model->type)) $model->type = SalesOrderType::DEFAULT;
        });

        static::creating(function ($model) {
            if (empty($model->description)) $model->description = "#Barang yang sudah dibeli tidak dapat dikembalikan. Terimakasih";
        });

        static::created(function ($model) {
            if (!isset($model->invoice_no)) {
                $model->invoice_no = self::getSoNumber();
                $model->save();
            }
        });
    }

    public function scopeTenanted(Builder $query, User $user = null)
    {
        if (!$user) {
            /** @var \App\Models\User $user */
            $user = auth('sanctum')->user();
        }

        if ($user->type->is(UserType::Spg)) return $query->where('spg_id', $user->id);
        return $query;
        // if ($user->hasRole('admin')) return $query;
        // return $query->whereIn('warehouse_id', $user->warehouses()->pluck('warehouse_id') ?? []);
    }

    public function getAdditionalDiscountPercentageAttribute()
    {
        return $this->raw_source['additional_discount'] ?? 0;
    }

    public function getAutoDiscountNominalAttribute(): int|float
    {
        if ($this->auto_discount == 0) return 0;
        if (isset($this->raw_source['auto_discount_nominal'])) return $this->raw_source['auto_discount_nominal'];
        return $this->details->sum('total_price') * $this->auto_discount / 100;
    }

    public function getVoucherCodeAttribute()
    {
        return $this->voucher?->code ?? $this->voucher?->description ?? '';
    }

    public function getVoucherTypeAttribute()
    {
        return $this->raw_source['voucher_type'] ?? DiscountType::NOMINAL;
    }
    public function getVoucherValueAttribute()
    {
        return $this->raw_source['voucher_value'] ?? 0;
    }
    public function getVoucherValueNominalAttribute()
    {
        return $this->raw_source['voucher_value_nominal'] ?? 0;
    }

    public function getPaymentAmountAttribute()
    {
        return $this->payments?->sum('amount') ?? 0;
    }

    public function getPaymentStatusAttribute()
    {
        if ($this->payment_amount == 0) {
            return 'none';
        } elseif ($this->payment_amount >= $this->price) {
            return 'paid';
        } else {
            return 'down_payment';
        }
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

    public function spg(): BelongsTo
    {
        return $this->belongsTo(User::class, 'spg_id');
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

    public function scopeWhereInvoice(Builder $query)
    {
        $query->where('is_invoice', true);
    }

    public function scopeDetailsHasDO(Builder $query, bool $value = true)
    {
        // if ($value) return $query->whereHas('details', fn ($q) => $q->has('deliveryOrderDetail'));
        // return $query->whereHas('details', fn ($q) => $q->doesntHave('deliveryOrderDetail'));
        return $query->whereHas('details', fn ($q) => $q->hasDeliveryOrder((bool)$value));
    }

    public function scopeHasSalesOrder(Builder $query, bool $value = true)
    {
        $query->when($value === true, fn ($q) => $q->whereNotNull('warehouse_id')->whereNotNull('invoice_no')->where('invoice_no', '!=', ''));
        // $query->when($value === true, fn ($q) => $q->whereNotNull('warehouse_id')->where(fn ($q) => $q->whereNotNull('invoice_no')->orWhere('invoice_no', '')));
    }
}
