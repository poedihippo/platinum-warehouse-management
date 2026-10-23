<?php

namespace App\Models;

use App\Enums\CompanyEnum;
use App\Enums\DocumentType;
use App\Services\NumberingService;
use App\Traits\FilterStartEndDate;
use App\Traits\Tenanted;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryOrder extends Model
{
    use FilterStartEndDate, Tenanted;

    protected $fillable = [
        'user_id',
        'warehouse_id',
        'reseller_id',
        'sales_order_id',
        'company',
        'invoice_no',
        'transaction_date',
        'shipment_estimation_datetime',
        'description',
        'is_done',
        'done_at',
    ];

    protected $casts = [
        'company' => CompanyEnum::class,
        'is_done' => 'boolean',
    ];

    protected $hidden = [
        'mergedSalesOrders',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->user_id = $model->user_id ?? auth('sanctum')->id();
            if (empty($model->description)) {
                $model->description = '#Barang yang sudah dibeli tidak dapat dikembalikan. Terimakasih';
            }

            if (empty($model->invoice_no) && $model->company) {
                $model->invoice_no = NumberingService::next($model->company, DocumentType::DO());
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

    public function details(): HasMany
    {
        return $this->hasMany(DeliveryOrderDetail::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /**
     * Sales orders that were merged into this delivery order's sales order.
     * When this relation is not empty, the delivery order is the result of a marketplace merge.
     */
    public function mergedSalesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class, 'merged_into_sales_order_id', 'sales_order_id');
    }

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

    public function scopeSearch(Builder $query, string $search)
    {
        return $query->where(function ($query) use ($search) {
            $query->where('invoice_no', 'like', "%{$search}%")
                ->orWhereHas('reseller', function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%");
                });
        });
    }
}
