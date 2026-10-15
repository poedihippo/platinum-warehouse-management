<?php

namespace App\Services;

use App\Http\Resources\SalesOrderResource;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderDetail;
use App\Models\Payment;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use App\Models\SalesOrderItem;
use App\Models\Warehouse;
use App\Pipes\Order\CalculateAdditionalDiscount;
use App\Pipes\Order\CalculateAdditionalFees;
use App\Pipes\Order\CalculateAutoDiscount;
use App\Pipes\Order\CalculateVoucher;
use App\Pipes\Order\CheckExpectedOrderPrice;
use App\Pipes\Order\FillOrderAttributes;
use App\Pipes\Order\FillOrderRecords;
use App\Pipes\Order\MakeOrderDetails;
use App\Pipes\Order\NormalizeLockedOrderItems;
use App\Pipes\Order\SaveOrder;
use App\Pipes\Order\UpdateOrder;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class SalesOrderService
{
    /**
     * validation total price between BE calculation with FE calculation
     *
     * @param  int|float  $totalPrice  total price from FE calculation
     * @param  array  $items  SO items data
     */
    public static function validateTotalPrice(int $totalPrice, ?int $shipmentFee, array $items): bool
    {
        $cekTotalPrice = 0;
        $pricePerItem = 0;
        foreach ($items as $item) {
            $pricePerItem = $item['unit_price'] * $item['qty'];
            $discount = $pricePerItem * ($item['discount'] / 100);
            $pricePerItem = $pricePerItem - $discount;
            if ($item['tax'] == 1) {
                $tax = $pricePerItem * 0.11;
                $pricePerItem = $pricePerItem + $tax;
            }
            $cekTotalPrice += $pricePerItem;
        }

        $cekTotalPrice += $shipmentFee;

        if ($cekTotalPrice != $totalPrice) {
            return false;
        }

        return true;
    }

    /**
     * count fulfilled_qty in sales_order_details
     *
     * @param  SalesOrderDetail  $salesOrderDetail
     */
    public static function countFulfilledQty(SalesOrderDetail|int $salesOrderDetail): void
    {
        if (! $salesOrderDetail instanceof SalesOrderDetail) {
            $salesOrderDetail = SalesOrderDetail::find($salesOrderDetail);
            if (! $salesOrderDetail) {
                return;
            }
        }

        $salesOrderDetail->refresh();

        $salesOrderDetail->update([
            'fulfilled_qty' => $salesOrderDetail->salesOrderItems()->where('is_parent', 0)->where('is_returned', 0)->count(),
        ]);
    }

    /**
     * Creates a new sales order.
     *
     * @param  SalesOrder  $salesOrder  The sales order object.
     * @param  bool  $isPerview  (optional) Flag indicating whether the order is a preview. Default is false.
     * @return SalesOrder The created sales order.
     */
    public static function createOrder(SalesOrder $salesOrder, bool $isPerview = false): SalesOrder
    {
        $pipes = [
            FillOrderAttributes::class,
            FillOrderRecords::class,
            NormalizeLockedOrderItems::class,
            MakeOrderDetails::class,
            CalculateAutoDiscount::class,
            CalculateVoucher::class,
            CalculateAdditionalDiscount::class,
            CalculateAdditionalFees::class,
            CheckExpectedOrderPrice::class,
        ];

        if (! $isPerview) {
            $pipes[] = SaveOrder::class;
        }

        return app(Pipeline::class)
            ->send($salesOrder)
            ->through($pipes)
            ->thenReturn();
    }

    /**
     * Updates a sales order.
     *
     * @param  SalesOrder  $salesOrder  The sales order object to be updated.
     * @param  bool  $isPerview  (optional) Flag indicating whether the order is a preview. Default is false.
     * @return SalesOrder The updated sales order.
     */
    public static function updateOrder(SalesOrder $salesOrder, bool $isPerview = false): SalesOrder
    {
        $pipes = [
            FillOrderAttributes::class,
            FillOrderRecords::class,
            NormalizeLockedOrderItems::class,
            MakeOrderDetails::class,
            CalculateAutoDiscount::class,
            CalculateVoucher::class,
            CalculateAdditionalDiscount::class,
            CalculateAdditionalFees::class,
            CheckExpectedOrderPrice::class,
        ];

        if (! $isPerview) {
            $pipes[] = UpdateOrder::class;
        }

        return app(Pipeline::class)
            ->send($salesOrder)
            ->through($pipes)
            ->thenReturn();
    }

    /**
     * Updates a sales order.
     *
     * @param  SalesOrder  $salesOrder  The sales order object to be updated.
     * @param  bool  $isPerview  (optional) Flag indicating whether the order is a preview. Default is false.
     * @return SalesOrder The updated sales order.
     */
    public static function convertOrderToSO(SalesOrder $salesOrder, bool $isPerview = false): SalesOrder
    {
        $pipes = [
            FillOrderAttributes::class,
            FillOrderRecords::class,
            NormalizeLockedOrderItems::class,
            MakeOrderDetails::class,
            CalculateAutoDiscount::class,
            CalculateVoucher::class,
            CalculateAdditionalDiscount::class,
            CalculateAdditionalFees::class,
            CheckExpectedOrderPrice::class,
        ];

        if (! $isPerview) {
            $pipes[] = UpdateOrder::class;
        }

        return app(Pipeline::class)
            ->send($salesOrder)
            ->through($pipes)
            ->thenReturn();
    }

    public static function index(int $perPage, ?callable $query = null)
    {
        $salesOrders = \Spatie\QueryBuilder\QueryBuilder::for(
            SalesOrder::tenanted()->withCount('details')->when($query, $query)
        )
            ->allowedFilters([
                'invoice_no',
                'is_invoice',
                AllowedFilter::exact('user_id'),
                AllowedFilter::exact('reseller_id'),
                AllowedFilter::exact('spg_id'),
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::exact('company'),
                AllowedFilter::exact('marketplace'),
                AllowedFilter::callback('is_marketplace', function ($q, $value) {
                    filter_var($value, FILTER_VALIDATE_BOOLEAN)
                        ? $q->whereNotNull('marketplace')
                        : $q->whereNull('marketplace');
                }),
                AllowedFilter::scope('has_sales_order', 'hasSalesOrder'),
                AllowedFilter::scope('has_delivery_order', 'detailsHasDO'),
                AllowedFilter::scope('has_related_so', 'hasSalesOrderItem'),
                AllowedFilter::scope('start_date'),
                AllowedFilter::scope('end_date'),
                AllowedFilter::scope('has_remaining_do', 'hasRemainingDo'),
                AllowedFilter::callback('search', function ($q, $value) {
                    $q->where('invoice_no', 'like', '%'.$value.'%')
                        ->orWhereHas('user', fn ($q) => $q->where('name', 'like', '%'.$value.'%'))
                        ->orWhereHas('reseller', fn ($q) => $q->where('name', 'like', '%'.$value.'%'))
                        ->orWhereHas('spg', fn ($q) => $q->where('name', 'like', '%'.$value.'%'));
                }),
            ])
            ->allowedSorts(['id', 'invoice_no', 'user_id', 'reseller_id', 'warehouse_id', 'transaction_date', 'shipment_estimation_datetime', 'created_at'])
            ->allowedIncludes(['details', 'warehouse', 'user', 'spg', 'payments', 'vouchers.category'])
            ->paginate($perPage);

        return SalesOrderResource::collection($salesOrders);
    }

    public static function show($id, ?callable $query = null)
    {
        $salesOrder = SalesOrder::when($query, $query)->findTenanted($id);

        return $salesOrder->load([
            'vouchers.category',
            'payments',
            'warehouse',
            'mergedSalesOrders:id,merged_into_sales_order_id',
            'details' => fn ($q) => $q->with([
                'warehouse',
                'productUnit' => fn ($q) => $q->with([
                    'product' => fn ($q) => $q->select('id', 'name', 'product_brand_id')->with([
                        'productBrand' => fn ($q) => $q->select('id', 'name'),
                    ]),
                    'uom' => fn ($q) => $q->select('id', 'name'),
                ]),
            ]),
            'user' => fn ($q) => $q->select('id', 'name', 'type'),
            'reseller' => fn ($q) => $q->select('id', 'name', 'type', 'type', 'email', 'phone', 'address'),
        ])->loadCount('details');
    }

    public static function print(int|string $id, string $type = 'print', ?callable $query = null)
    {
        if ($type == 'print') {
            $salesOrder = SalesOrder::when($query, $query, $query)->findTenanted($id);
            $view = 'pdf.salesOrders.salesOrder';
        } else {
            $salesOrder = SalesOrder::when($query, $query, $query)->find($id);
            if (! $salesOrder) {
                return redirect()->away('https://platinumadisentosa.com');
            }
            $view = 'pdf.salesOrders.salesOrderInvoice';
        }

        $salesOrder->load([
            'reseller',
            'vouchers.category',
            'details' => fn ($q) => $q->with('productUnit.product'),
        ])->loadSum('payments', 'amount');

        $salesOrderDetails = $salesOrder->details->chunk(10);

        $lastOrderDetailsKey = $salesOrderDetails->keys()->last();
        $maxProductsBlackSpace = 10;

        $spellTotalPrice = \NumberToWords\NumberToWords::transformNumber('en', $salesOrder->price);
        $bankTransferInfo = \App\Services\SettingService::bankTransferInfo();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::setPaper('a4', 'landscape')->loadView($view, ['salesOrder' => $salesOrder, 'salesOrderDetails' => $salesOrderDetails, 'maxProductsBlackSpace' => $maxProductsBlackSpace, 'lastOrderDetailsKey' => $lastOrderDetailsKey, 'spellTotalPrice' => $spellTotalPrice, 'bankTransferInfo' => $bankTransferInfo]);

        return $pdf->download('sales-order-'.$salesOrder->invoice_no.'.pdf');
    }

    public static function exportXml($id, ?callable $query = null)
    {
        $productUnitColumns = ['id', 'uom_id', 'refer_id', 'code', 'name'];
        $uomColumns = 'uom:id,name';
        $salesOrder = SalesOrder::when($query, $query, null)->findTenanted($id);
        $salesOrder->load([
            'reseller',
            'details' => fn ($q) => $q->with('productUnit', fn ($q) => $q
                ->select($productUnitColumns)
                ->with([
                    $uomColumns,
                    'refer' => fn ($q) => $q->select($productUnitColumns)->with($uomColumns),
                    'relations' => fn ($q) => $q->with('relatedProductUnit', fn ($q) => $q->select($productUnitColumns)->with($uomColumns)),
                ])),
        ]);

        // $salesOrder->load(['reseller', 'details' => fn($q) => $q->with('packaging', 'productUnit')]);
        return response(view('xml.salesOrders.salesOrder')->with(compact('salesOrder')), 200, [
            'Content-Type' => 'application/xml',
            // use your required mime type
            'Content-Disposition' => 'attachment; filename="Sales Order '.$salesOrder->invoice_no.'.xml"',
        ]);
    }

    public static function getWhatsappUrl(SalesOrder $salesOrder, ?string $idHash = null)
    {
        $warehouseName = $salesOrder->warehouse?->company_name ? $salesOrder->warehouse->company_name : $salesOrder->warehouse->name;

        $message = 'Terima kasih atas pesanannya di '.($warehouseName ?? '').'. Detail pesanan:';
        $message .= PHP_EOL;
        $message .= PHP_EOL;

        $order = 1;
        foreach ($salesOrder->details as $salesOrderDetail) {
            $message .= $order++.'. '.$salesOrderDetail->productUnit->name.' x '.$salesOrderDetail->qty.' = *Rp '.number_format((float) $salesOrderDetail->total_price, 0, ',', '.').'*';
            $message .= PHP_EOL;
        }

        $message .= PHP_EOL;
        $message .= 'Subtotal                       : *Rp '.number_format((float) $salesOrder->details->sum('total_price'), 0, ',', '.').'*';

        foreach ($salesOrder->auto_discount_details as $discount) {
            $message .= PHP_EOL;
            $message .= 'Diskon Pameran ('.($discount['percent'] ?? 0).'%) : *Rp '.number_format((float) ($discount['discount_nominal'] ?? 0), 0, ',', '.').'*';
        }

        foreach ($salesOrder->vouchers as $voucher) {
            $message .= PHP_EOL;
            $message .= 'Voucher ('.($voucher->description ?: $voucher->code).') : *Rp '.number_format((float) $voucher->pivot->discount_amount, 0, ',', '.').'*';
        }

        if ($salesOrder->additional_discount > 0) {
            $message .= PHP_EOL;
            $message .= 'Additional Discount : *Rp '.number_format((float) $salesOrder->additional_discount, 0, ',', '.').'*';
        }

        if ($salesOrder->shipment_fee > 0) {
            $message .= PHP_EOL;
            $message .= 'Delivery Fee                : *Rp '.number_format((float) $salesOrder->shipment_fee, 0, ',', '.').'*';
        }

        $message .= PHP_EOL;
        $message .= 'Grand Total                    : *Rp '.number_format((float) $salesOrder->price, 0, ',', '.').'*';
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= $salesOrder->description;
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= 'Download invoice :';
        $message .= PHP_EOL;
        $message .= 'https://platinumadisentosa.com/invoices/'.($idHash ?? Crypt::encryptString($salesOrder->id)).'/print';

        $phone = $salesOrder->reseller?->phone;
        if (empty($phone)) {
            return null;
        }

        if ($phone[0] == '0') {
            $phone = substr($phone, 1);
        }

        return sprintf('https://web.whatsapp.com/send/?phone=%s&text=%s', $phone, urlencode($message));
    }

    public static function getDefaultInvoiceNo(string $warehouseCode): string
    {
        return sprintf(config('app.format_invoice_no'), date('Y'), date('m'), date('d'), sprintf('%04s', config('app.start_invoice_no', 90)), $warehouseCode);
    }

    public static function getSoNumber(Warehouse $warehouse): string
    {
        $lastInoviceNo = SalesOrder::where('is_invoice', true)
            ->whereDate('created_at', date('Y-m-d'))
            ->where('warehouse_id', $warehouse->id)
            ->where('invoice_no', 'like', '%'.config('app.format_invoice_prefix').'%')
            ->orderByDesc('invoice_no')
            ->first(['invoice_no']);

        if ($lastInoviceNo) {
            try {
                $lastInoviceNo = explode('/', $lastInoviceNo->invoice_no)[3];
                $lastInoviceNo = sprintf(config('app.format_invoice_no'), date('Y'), date('m'), date('d'), sprintf('%04s', (int) $lastInoviceNo + 1), $warehouse->code);
            } catch (\Exception $e) {
                $lastInoviceNo = self::getDefaultInvoiceNo($warehouse->code);
            }
        } else {
            $lastInoviceNo = self::getDefaultInvoiceNo($warehouse->code);
        }

        return $lastInoviceNo;
    }

    /**
     * Merge multiple marketplace Sales Orders into a single new marketplace Sales Order.
     *
     * The source Sales Orders are kept as documentation and marked as merged. A Delivery
     * Order is always created for the merged order.
     */
    public static function mergeMarketplaceOrders(array $data): SalesOrder
    {
        return DB::transaction(function () use ($data) {
            $sourceIds = collect($data['sales_order_ids'])->unique()->values();

            if ($sourceIds->count() < 2) {
                throw new BadRequestHttpException('Minimal 2 Sales Order harus dipilih untuk di-merge.');
            }

            $sources = SalesOrder::tenanted()
                ->whereIn('id', $sourceIds->all())
                ->lockForUpdate()
                ->get();

            if ($sources->count() !== $sourceIds->count()) {
                throw new BadRequestHttpException('Sales Order tidak ditemukan.');
            }

            static::assertSourcesMergeable($sources);

            $firstSource = $sources->first();

            $rawSource = [
                'user_id' => auth('sanctum')->id(),
                'company' => $firstSource->getRawOriginal('company'),
                'reseller_id' => $data['reseller_id'] ?? null,
                'warehouse_id' => $firstSource->warehouse_id,
                'invoice_no' => $data['invoice_no'],
                'transaction_date' => $data['transaction_date'],
                'shipment_estimation_datetime' => $data['transaction_date'],
                'shipment_fee' => 0,
                'additional_discount' => 0,
                'is_auto_discount_enabled' => false,
                'description' => $data['description'] ?? null,
                'marketplace' => $data['marketplace'] ?? null,
                'items' => static::buildMergedOrderItems($sourceIds->all()),
            ];

            $mergedSalesOrder = static::createOrder(SalesOrder::make(['raw_source' => $rawSource]));

            if (! $mergedSalesOrder->marketplace) {
                DeliveryOrderService::createForSalesOrder($mergedSalesOrder, true);
            }

            SalesOrder::whereIn('id', $sourceIds->all())
                ->update(['merged_into_sales_order_id' => $mergedSalesOrder->id]);

            return $mergedSalesOrder;
        });
    }

    /**
     * Delete a merged Sales Order, its Delivery Order, and release the source Sales Orders
     * so they become available to be merged again.
     */
    public static function deleteMergeResult(SalesOrder $salesOrder): void
    {
        DB::transaction(function () use ($salesOrder) {
            $deliveryOrderIds = $salesOrder->deliveryOrderDetails()
                ->pluck('delivery_order_id')
                ->unique()
                ->values();

            $deliveryOrders = DeliveryOrder::whereIn('id', $deliveryOrderIds)
                ->lockForUpdate()
                ->get();

            foreach ($deliveryOrders as $deliveryOrder) {
                if ($deliveryOrder->is_done) {
                    throw new BadRequestHttpException('Delivery Order sudah diselesaikan. Batalkan status Done terlebih dahulu sebelum menghapus Sales Order hasil merge.');
                }
            }

            $deliveryOrders->each(fn (DeliveryOrder $deliveryOrder) => DeliveryOrderService::deleteDeliveryOrder($deliveryOrder));

            $salesOrder->mergedSalesOrders()->update(['merged_into_sales_order_id' => null]);

            $salesOrder->delete();
        });
    }

    /**
     * Validate that every given Sales Order can be merged together.
     *
     * @param  Collection<int, SalesOrder>  $sources
     */
    private static function assertSourcesMergeable(Collection $sources): void
    {
        if (SalesOrder::whereIn('merged_into_sales_order_id', $sources->pluck('id'))->exists()) {
            throw new BadRequestHttpException('Sales Order hasil merge tidak dapat di-merge kembali.');
        }

        $warehouseId = null;
        $company = null;

        foreach ($sources as $source) {
            if (is_null($source->marketplace)) {
                throw new BadRequestHttpException('Sales Order yang dipilih harus berupa Sales Order marketplace.');
            }

            if ($source->isMergeSalesOrder()) {
                throw new BadRequestHttpException('Sales Order sudah pernah di-merge.');
            }

            $sourceCompany = $source->getRawOriginal('company');

            if (is_null($warehouseId)) {
                $warehouseId = $source->warehouse_id;
                $company = $sourceCompany;
            } elseif ((int) $source->warehouse_id !== (int) $warehouseId) {
                throw new BadRequestHttpException('Semua Sales Order harus berasal dari warehouse yang sama.');
            }

            if ($sourceCompany !== $company) {
                throw new BadRequestHttpException('Semua Sales Order harus memiliki company yang sama.');
            }
        }

        $detailIds = SalesOrderDetail::whereIn('sales_order_id', $sources->pluck('id'))->pluck('id');

        $hasVerifiedStock = SalesOrderItem::whereIn('sales_order_detail_id', $detailIds)
            ->whereNotNull('stock_id')
            ->whereNotReturned()
            ->exists();

        if ($hasVerifiedStock) {
            throw new BadRequestHttpException('Sales Order sudah memiliki stock terverifikasi dan tidak dapat di-merge.');
        }

        $deliveryOrderIds = DeliveryOrderDetail::whereIn('sales_order_detail_id', $detailIds)
            ->pluck('delivery_order_id')
            ->unique();

        $hasDoneDeliveryOrder = $deliveryOrderIds->isNotEmpty()
            && DeliveryOrder::whereIn('id', $deliveryOrderIds)->where('is_done', true)->exists();

        if ($hasDoneDeliveryOrder) {
            throw new BadRequestHttpException('Sales Order memiliki Delivery Order yang sudah diselesaikan dan tidak dapat di-merge.');
        }

        $hasPayment = Payment::whereIn('sales_order_id', $sources->pluck('id'))->exists();

        if ($hasPayment) {
            throw new BadRequestHttpException('Sales Order sudah memiliki pembayaran dan tidak dapat di-merge.');
        }
    }

    /**
     * Combine the source details into order items, grouping identical product units and prices.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function buildMergedOrderItems(array $salesOrderIds): array
    {
        return SalesOrderDetail::whereIn('sales_order_id', $salesOrderIds)
            ->get()
            ->groupBy(fn (SalesOrderDetail $detail) => implode('|', [
                $detail->product_unit_id,
                $detail->unit_price,
                $detail->discount,
                $detail->tax,
            ]))
            ->map(function (Collection $group) {
                /** @var SalesOrderDetail $first */
                $first = $group->first();

                return [
                    'product_unit_id' => (int) $first->product_unit_id,
                    'qty' => (int) $group->sum('qty'),
                    'unit_price' => (int) $first->unit_price,
                    'discount' => (int) $first->discount,
                    'tax' => (int) $first->tax === 11 ? 1 : 0,
                    'total_price' => (int) $group->sum('total_price'),
                    'warehouse_id' => $first->warehouse_id,
                ];
            })
            ->values()
            ->all();
    }
}
