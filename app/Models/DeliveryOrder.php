<?php

namespace App\Models;

use App\Enums\SettingEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class DeliveryOrder extends Model
{
    use SoftDeletes;

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
            if (empty($model->description)) $model->description = '#Barang yang sudah dibeli tidak dapat dikembalikan. Terimakasih';
        });

        static::created(function ($model) {
            if (empty($model->invoice_no)) {
                $model->invoice_no = self::getDoNumber();
                $model->save();
            }
        });

        static::saved(function ($model) {
            if ($model->isDirty('is_done')) {
                $model->done_at = now();
            }
        });

        // static::deleted(function ($model) {
        //     $model->salesOrder?->details?->each(fn ($detail) => SalesOrderItem::where('sales_order_detail_id', $detail->id)->delete());
        // });
    }

    public function details() : HasMany
    {
        return $this->hasMany(DeliveryOrderDetail::class);
    }

    // public function salesOrder()
    // {
    //     return $this->belongsTo(SalesOrder::class);
    // }

    public function user() : BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function warehouse() : BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function reseller() : BelongsTo
    {
        return $this->belongsTo(User::class, 'reseller_id');
    }

    public static function getDoNumber() : string
    {
        $key = SettingEnum::DO_NUMBER;
        return DB::transaction(function () use ($key) {
            // Get current value to use. We use lock for update
            // to prevent other thread to read this row until we update it
            $lastDoNumber = DB::table('settings')
                ->where('key', $key)
                ->lockForUpdate()
                ->first('value')?->value ?? null;

            if (isset($lastDoNumber) && ! is_null($lastDoNumber) && $lastDoNumber != '') {
                $arrayLastDoNumber = explode('/', $lastDoNumber);

                if (is_array($arrayLastDoNumber) && count($arrayLastDoNumber) == 5 && date('m') == $arrayLastDoNumber[2] && date('y') == $arrayLastDoNumber[3]) {
                    $arrayLastDoNumber[4] = sprintf('%02d', (int) $arrayLastDoNumber[4] + 1);
                    $nextLastDoNumber = implode('/', $arrayLastDoNumber);
                } else {
                    $lastDoNumber = sprintf('PAS/DO/%s/%s/01', date('m'), date('y'));
                    $nextLastDoNumber = sprintf('PAS/DO/%s/%s/02', date('m'), date('y'));
                }
            } else {
                $lastDoNumber = sprintf('PAS/DO/%s/%s/01', date('m'), date('y'));
                $nextLastDoNumber = sprintf('PAS/DO/%s/%s/02', date('m'), date('y'));
            }

            // update the value with $nextLastDoNumber
            DB::table('settings')
                ->where('key', $key)
                ->update(['value' => $nextLastDoNumber]);

            return $lastDoNumber;
        });
    }
}
