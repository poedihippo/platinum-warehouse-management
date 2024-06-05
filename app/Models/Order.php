<?php

namespace App\Models;

use App\Enums\SalesOrderType;
use App\Traits\FilterStartEndDate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use FilterStartEndDate;

    protected $fillable = [
        'user_id',
        'customer_id',
        'raw_source',
        'records',
        'type',
        'price',
        'description',
    ];

    protected $casts = [
        'raw_source' => 'array',
        'records' => 'array',
        'price' => 'integer',
        'type' => SalesOrderType::class
    ];

    protected static function booted()
    {
        static::saving(function ($model) {
            if (empty($model->type)) $model->type = SalesOrderType::PICKUP;
        });

        static::creating(function ($model) {
            $model->user_id = auth('sanctum')->id();
            if (empty($model->description)) $model->description = "#Barang yang sudah dibeli tidak dapat dikembalikan. Terimakasih";
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(OrderDetail::class);
    }
}
