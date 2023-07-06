<?php

namespace App\Models;

use App\Enums\SettingEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class DeliveryOrder extends Model
{
    protected $fillable = [
        'user_id',
        'warehouse_id',
        'reseller_id',
        'invoice_no',
        'transaction_date',
        'shipment_estimation_datetime',
        'description',
        'is_done',
        'done_at',
    ];

    protected $casts = [
        'is_done' => 'boolean'
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->user_id = auth()->user()->id;
        });

        static::created(function ($model) {
            $model->invoice_no = self::getSoNumber();
            $model->save();
        });

        static::saved(function ($model) {
            if ($model->isDirty('is_done')) {
                $model->done_at = now();
            }
        });

        static::deleted(function ($model) {
            $model->salesOrder?->details?->each(fn ($detail) => SalesOrderItem::where('sales_order_detail_id', $detail->id)->delete());
        });
    }

    public function details(): HasMany
    {
        return $this->hasMany(DeliveryOrderDetail::class);
    }

    // public function salesOrder()
    // {
    //     return $this->belongsTo(SalesOrder::class);
    // }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reseller_id');
    }

    public static function getSoNumber(): string
    {
        $key = SettingEnum::DO_NUMBER;
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
                    $arrayLastSoNumber[4] = sprintf('%02d', (int)$arrayLastSoNumber[4] + 1);
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
                ->update(['value' => $nextLastSoNumber]);

            return $lastSoNumber;
        });
    }
}
