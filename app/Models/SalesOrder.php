<?php

namespace App\Models;

use App\Enums\SettingEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SalesOrder extends Model
{
    protected $fillable = [
        'user_id',
        'reseller_id',
        'warehouse_id',
        'invoice_no',
        'transaction_date',
        'shipment_estimation_datetime',
        'price',
        'description',
    ];

    protected $casts = [
        'price' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->user_id = auth()->user()->id;
            if (empty($model->description)) $model->description = '#Barang yang sudah dibeli tidak dapat dikembalikan. Terimakasih';
        });

        static::created(function ($model) {
            $model->invoice_no = self::getSoNumber();
            $model->save();
        });
    }

    // public function deliveryOrder()
    // {
    //     return $this->hasOne(DeliveryOrder::class);
    // }

    public function details()
    {
        return $this->hasMany(SalesOrderDetail::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reseller()
    {
        return $this->belongsTo(User::class, 'reseller_id');
    }

    public function warehouse()
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
