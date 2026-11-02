<?php

namespace App\Http\Services\Order;

use App\Enums\UserType;
use App\Exceptions\ApiResponseException;
use App\Interfaces\Repositories\SalesOrder\SalesOrderRepositoryInterface;
use App\Interfaces\Repositories\StockHistory\StockHistoryRepositoryInterface;
use App\Interfaces\Services\Order\OrderServiceInterface;
use App\Models\SalesOrder;
use App\Models\StockProductUnit;
use App\Pipes\Order\CalculateAdditionalDiscount;
use App\Pipes\Order\CalculateAdditionalFees;
use App\Pipes\Order\CalculateAutoDiscount;
use App\Pipes\Order\CalculateVoucher;
use App\Pipes\Order\CheckExpectedOrderPrice;
use App\Pipes\Order\FillOrderAttributes;
use App\Pipes\Order\FillOrderRecords;
use App\Pipes\Order\MakeOrderDetails;
use App\Pipes\Order\Spg\ConvertToSO;
use App\Pipes\Order\Spg\SaveOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pipeline\Pipeline;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * SPG quotations: sales orders with is_invoice = true and an empty invoice_no,
 * converted into IIPE invoices by an admin.
 */
class OrderService implements OrderServiceInterface
{
    private const PIPES = [
        FillOrderAttributes::class,
        FillOrderRecords::class,
        MakeOrderDetails::class,
        CalculateAutoDiscount::class,
        CalculateVoucher::class,
        CalculateAdditionalDiscount::class,
        CalculateAdditionalFees::class,
        CheckExpectedOrderPrice::class,
    ];

    public function __construct(
        protected SalesOrderRepositoryInterface $repository,
        protected StockHistoryRepositoryInterface $stockHistoryRepository,
    ) {
    }

    public function paginateIndex(int $perPage): LengthAwarePaginator
    {
        return $this->repository->findAllPaginate(
            $perPage,
            fn ($query) => $query->tenanted()->withCount('details'),
            [
                'type',
                AllowedFilter::exact('user_id'),
                AllowedFilter::exact('spg_id'),
                AllowedFilter::exact('reseller_id'),
                AllowedFilter::scope('has_sales_order', 'hasSalesOrder'),
                AllowedFilter::scope('start_date'),
                AllowedFilter::scope('end_date'),
            ],
            ['details', 'user', 'spg', 'reseller'],
            ['id', 'user_id', 'spg_id', 'reseller_id', 'created_at'],
        );
    }

    public function store(mixed $rawSource, mixed $isPreview): SalesOrder
    {
        $rawSource['invoice_no'] = '';

        $pipes = self::PIPES;

        if (! $isPreview ?? true) {
            $pipes[] = SaveOrder::class;
        }

        return app(Pipeline::class)
            ->send(SalesOrder::make(['raw_source' => $rawSource, 'is_invoice' => true]))
            ->through($pipes)
            ->thenReturn();
    }

    /**
     * Known quirk kept as is: $isPreview is true when the request is NOT a preview.
     */
    public function convert(SalesOrder $order, mixed $rawSource, mixed $isPreview): SalesOrder
    {
        $user = auth('sanctum')->user();
        if ($user->type->is(UserType::Spg)) {
            throw new ApiResponseException('Anda tidak dapat mengkonversi invoice ini', 400);
        }

        if (! empty($order->invoice_no)) {
            throw new ApiResponseException('Quotation sudah diconvert menjadi Invoice', 400);
        }

        $order->raw_source = $rawSource;
        $isPreview = ! $isPreview ?? true;

        $pipes = self::PIPES;

        if ($isPreview) {
            $pipes[] = ConvertToSO::class;
        }

        $salesOrder = app(Pipeline::class)
            ->send($order)
            ->through($pipes)
            ->thenReturn();

        if ($salesOrder && ! $isPreview === false) {
            $salesOrder->details->each(function ($salesOrderDetail) use ($salesOrder) {
                $stockProductUnit = StockProductUnit::where('warehouse_id', $salesOrderDetail->warehouse_id)
                    ->where('product_unit_id', $salesOrderDetail->product_unit_id)
                    ->first(['id']);

                $this->stockHistoryRepository->record($salesOrderDetail, [
                    'user_id' => $salesOrder->user_id,
                    'stock_product_unit_id' => $stockProductUnit->id,
                    'value' => $salesOrderDetail->qty,
                    'is_increment' => 0,
                    'description' => 'Create SO invoice '.$salesOrder->invoice_no,
                    'ip' => request()->ip(),
                    'agent' => request()->header('user-agent'),
                ]);
            });
        }

        return $salesOrder;
    }

    public function destroyQuotation(SalesOrder $order): void
    {
        $user = auth('sanctum')->user();
        if ($user->type->is(UserType::Spg) && $order->spg_id != $user->id) {
            throw new ApiResponseException('Anda tidak dapat menghapus invoice ini', 400);
        }

        if (! empty($order->invoice_no)) {
            throw new ApiResponseException('Quotation sudah diconvert menjadi Invoice. Tidak dapat dihapus', 400);
        }

        $order->forceDelete();
    }
}
