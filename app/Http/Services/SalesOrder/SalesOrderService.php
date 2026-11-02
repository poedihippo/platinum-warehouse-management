<?php

namespace App\Http\Services\SalesOrder;

use App\Exceptions\ApiResponseException;
use App\Helpers\ContentDispositionHelper;
use App\Http\Services\BaseService;
use App\Interfaces\Repositories\SalesOrder\SalesOrderRepositoryInterface;
use App\Interfaces\Repositories\StockHistory\StockHistoryRepositoryInterface;
use App\Interfaces\Services\DeliveryOrder\DeliveryOrderServiceInterface;
use App\Interfaces\Services\DocumentCounter\NumberingServiceInterface;
use App\Interfaces\Services\SalesOrder\SalesOrderServiceInterface;
use App\Interfaces\Services\Setting\SettingServiceInterface;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderDetail;
use App\Models\ProductUnit;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use App\Models\SalesOrderItem;
use App\Models\Stock;
use App\Models\StockProductUnit;
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
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Response;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class SalesOrderService extends BaseService implements SalesOrderServiceInterface
{
    public function __construct(
        protected SalesOrderRepositoryInterface $repository,
        protected DeliveryOrderServiceInterface $deliveryOrderService,
        protected SettingServiceInterface $settingService,
        protected NumberingServiceInterface $numberingService,
        protected StockHistoryRepositoryInterface $stockHistoryRepository,
    ) {
        parent::__construct($repository);
    }

    /**
     * validation total price between BE calculation with FE calculation
     *
     * @param  int|float  $totalPrice  total price from FE calculation
     * @param  array  $items  SO items data
     */
    public function validateTotalPrice(int $totalPrice, ?int $shipmentFee, array $items): bool
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
     * Verify (scan) a single stock into a Sales Order detail, reserving it against the order.
     *
     * Used by both the invoice (kasir) and marketplace flows. A grouped stock is stored as a
     * parent item with its children; a regular stock becomes a single item.
     *
     * @throws BadRequestHttpException when the stock does not match, is already verified, or the qty is already fulfilled
     */
    public function verifyStockItem(SalesOrderDetail $salesOrderDetail, string|int $stockId): SalesOrderItem
    {
        $salesOrderDetail->load(['productUnit' => fn ($q) => $q->select('id', 'refer_id')]);

        $stock = Stock::where('id', $stockId)
            ->whereHas(
                'stockProductUnit',
                fn ($q) => $q->where('warehouse_id', $salesOrderDetail->warehouse_id)
                    ->when(
                        $salesOrderDetail->productUnit->refer_id,
                        fn ($q) => $q->where('product_unit_id', $salesOrderDetail->productUnit->refer_id),
                        fn ($q) => $q->where('product_unit_id', $salesOrderDetail->product_unit_id),
                    )
            )
            ->first();

        if (! $stock) {
            throw new BadRequestHttpException('Stok produk tidak sesuai');
        }

        if (SalesOrderItem::where('stock_id', $stock->id)->whereNotReturned()->exists()) {
            throw new BadRequestHttpException('Product sudah pernah di verifikasi');
        }

        $fulfilledQty = $salesOrderDetail->salesOrderItems()->where('is_parent', 0)->count();
        if ($fulfilledQty >= $salesOrderDetail->qty) {
            throw new BadRequestHttpException('Qty sudah terpenuhi');
        }

        $stock->load(['childs' => fn ($q) => $q->select('id', 'parent_id', 'stock_product_unit_id')]);

        if ($stock->hasIncompleteGrouping()) {
            throw new BadRequestHttpException(Stock::INCOMPLETE_GROUPING_MESSAGE);
        }

        return DB::transaction(function () use ($salesOrderDetail, $stock) {
            if ($stock->childs->isNotEmpty()) {
                $salesOrderItem = $salesOrderDetail->salesOrderItems()->create([
                    'stock_id' => $stock->id,
                    'is_parent' => true,
                ]);

                $salesOrderItem->childs()->createMany(
                    $stock->childs->map(fn ($child) => [
                        'sales_order_detail_id' => $salesOrderDetail->id,
                        'stock_id' => $child->id,
                    ])->all()
                );
            } else {
                $salesOrderItem = $salesOrderDetail->salesOrderItems()->create([
                    'stock_id' => $stock->id,
                    'is_parent' => false,
                ]);
            }

            $this->repository->countFulfilledQty($salesOrderDetail);

            return $salesOrderItem;
        });
    }

    /**
     * Release every scanned (verified) stock of a Sales Order back to available stock and
     * record the movement in the stock history.
     */
    public function releaseScannedStock(SalesOrder $salesOrder): void
    {
        $details = $salesOrder->details()->whereHas('salesOrderItems')->get();

        if ($details->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($salesOrder, $details) {
            foreach ($details as $salesOrderDetail) {
                $stockProductUnitId = StockProductUnit::where('warehouse_id', $salesOrderDetail->warehouse_id)
                    ->where('product_unit_id', $salesOrderDetail->product_unit_id)
                    ->value('id');

                if ($stockProductUnitId) {
                    $this->stockHistoryRepository->record($salesOrderDetail, [
                        'user_id' => $salesOrder->user_id,
                        'stock_product_unit_id' => $stockProductUnitId,
                        'value' => $salesOrderDetail->fulfilled_qty,
                        'is_increment' => 1,
                        'description' => 'Return stock from delete SO '.$salesOrder->invoice_no,
                        'ip' => request()->ip(),
                        'agent' => request()->header('user-agent'),
                    ]);
                }

                $salesOrderDetail->salesOrderItems()->delete();
            }
        });
    }

    /**
     * Creates a new sales order.
     *
     * @param  SalesOrder  $salesOrder  The sales order object.
     * @param  bool  $isPerview  (optional) Flag indicating whether the order is a preview. Default is false.
     * @return SalesOrder The created sales order.
     */
    public function storeOrder(array $validated, bool $isPreview): SalesOrder
    {
        return DB::transaction(
            fn () => $this->createOrder(SalesOrder::make(['raw_source' => $validated]), $isPreview),
            3
        );
    }

    public function createOrder(SalesOrder $salesOrder, bool $isPerview = false): SalesOrder
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
    public function updateOrder(SalesOrder $salesOrder, bool $isPerview = false): SalesOrder
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
    public function convertOrderToSO(SalesOrder $salesOrder, bool $isPerview = false): SalesOrder
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

    public function index(int $perPage, ?callable $query = null)
    {
        $salesOrders = \Spatie\QueryBuilder\QueryBuilder::for(
            SalesOrder::tenanted()->with('mergedSalesOrders:id,merged_into_sales_order_id,invoice_no')->withCount([
                'details',
                'details as unverified_details_count' => fn ($q) => $q->whereColumn('fulfilled_qty', '<', 'qty'),
                'deliveryOrders',
                'deliveryOrders as pending_delivery_orders_count' => fn ($q) => $q->where('is_done', false),
                'mergedSalesOrders',
            ])->when($query, $query)
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
                        ? $q->isWhereMarketplace(true)
                        : $q->isWhereMarketplace(false);
                }),
                AllowedFilter::scope('has_sales_order', 'hasSalesOrder'),
                AllowedFilter::scope('has_delivery_order', 'detailsHasDO'),
                AllowedFilter::scope('has_related_so', 'hasSalesOrderItem'),
                AllowedFilter::scope('start_date'),
                AllowedFilter::scope('end_date'),
                AllowedFilter::scope('has_remaining_do', 'hasRemainingDo'),
                AllowedFilter::scope('is_mergeable', 'mergeable'),
                AllowedFilter::scope('search'),
            ])
            ->allowedSorts(['id', 'invoice_no', 'user_id', 'reseller_id', 'warehouse_id', 'transaction_date', 'shipment_estimation_datetime', 'created_at'])
            ->allowedIncludes([
                'details',
                'warehouse',
                'user',
                'spg',
                'payments',
                'vouchers.category',
                AllowedInclude::callback('deliveryOrders', fn ($q) => $q->select('id', 'sales_order_id', 'invoice_no', 'is_done')),
            ])
            ->paginate($perPage);

        return $salesOrders;
    }

    public function show($id, ?callable $query = null)
    {
        $salesOrder = SalesOrder::when($query, $query)->findTenanted($id);

        return $salesOrder->load([
            'vouchers.category',
            'payments',
            'warehouse',
            'mergedSalesOrders:id,merged_into_sales_order_id,invoice_no',
            'deliveryOrders' => fn ($q) => $q->select('id', 'sales_order_id', 'invoice_no', 'is_done'),
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
        ])->loadCount([
            'details',
            'details as unverified_details_count' => fn ($q) => $q->whereColumn('fulfilled_qty', '<', 'qty'),
            'deliveryOrders',
            'deliveryOrders as pending_delivery_orders_count' => fn ($q) => $q->where('is_done', false),
            'mergedSalesOrders',
        ]);
    }

    public function print(int|string $id, string $type = 'print', ?callable $query = null, string $mode = 'download')
    {
        if ($type == 'print') {
            $salesOrder = SalesOrder::when($query, $query)->findTenanted($id);
            $view = 'pdf.salesOrders.salesOrder';
        } else {
            $salesOrder = SalesOrder::when($query, $query)->find($id);
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
        $bankTransferInfo = $this->settingService->bankTransferInfo();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::setPaper('a4', 'landscape')->loadView($view, [
            'salesOrder' => $salesOrder,
            'salesOrderDetails' => $salesOrderDetails,
            'maxProductsBlackSpace' => $maxProductsBlackSpace,
            'lastOrderDetailsKey' => $lastOrderDetailsKey,
            'spellTotalPrice' => $spellTotalPrice,
            'bankTransferInfo' => $bankTransferInfo,
        ]);

        $filename = 'sales-order-'.$salesOrder->invoice_no.'.pdf';
        $mode = strtolower($mode);

        if (in_array($mode, ['preview', 'inline', 'view'], true)) {
            return response($pdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
                'Cache-Control' => 'private, max-age=0, must-revalidate',
                'Pragma' => 'no-cache',
            ]);
        }

        return $pdf->download($filename);
    }

    public function exportXml($id, ?callable $query = null)
    {
        $productUnitColumns = ['id', 'uom_id', 'refer_id', 'refer_qty', 'code', 'name'];
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

        $filename = ContentDispositionHelper::filename(
            $salesOrder->invoice_no,
            'sales-order-'.$salesOrder->id
        );

        return response(view('xml.salesOrders.salesOrder')->with(compact('salesOrder')), 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => ContentDispositionHelper::attachmentHeader($filename),
        ]);
    }

    public function getWhatsappUrl(SalesOrder $salesOrder, ?string $idHash = null)
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

    /**
     * Merge multiple marketplace Sales Orders into a single new marketplace Sales Order.
     *
     * The source Sales Orders are kept as documentation and marked as merged. A Delivery
     * Order is always created for the merged order.
     */
    public function mergeMarketplaceOrders(array $data): SalesOrder
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

            $this->assertSourcesMergeable($sources);

            $firstSource = $sources->first();

            // Document numbers come from the request only while the counter is disabled.
            $manualInvoiceNo = $this->numberingService->enabled() ? null : ($data['invoice_no'] ?? null);
            $manualDeliveryOrderInvoiceNo = $this->numberingService->enabled() ? null : ($data['delivery_order_invoice_no'] ?? null);

            $rawSource = [
                'user_id' => auth('sanctum')->id(),
                'company' => $firstSource->getRawOriginal('company'),
                'reseller_id' => $data['reseller_id'] ?? null,
                'warehouse_id' => $firstSource->warehouse_id,
                'transaction_date' => $data['transaction_date'],
                'shipment_estimation_datetime' => $data['transaction_date'],
                'shipment_fee' => 0,
                'additional_discount' => 0,
                'is_auto_discount_enabled' => false,
                'description' => $data['description'] ?? null,
                'marketplace' => $data['marketplace'] ?? null,
                'items' => $this->buildMergedOrderItems($sourceIds->all()),
            ];

            if ($manualInvoiceNo !== null) {
                $rawSource['invoice_no'] = $manualInvoiceNo;
            }

            $mergedSalesOrder = $this->createOrder(SalesOrder::make(['raw_source' => $rawSource]));

            $deliveryOrder = DeliveryOrder::where('sales_order_id', $mergedSalesOrder->id)->first()
                ?? $this->deliveryOrderService->createForSalesOrder($mergedSalesOrder, true, $manualDeliveryOrderInvoiceNo);

            $this->copyVerifiedStockItems($mergedSalesOrder, $deliveryOrder, $sourceIds->all());

            $deliveryOrder->update(['is_done' => true, 'done_at' => now()]);

            SalesOrder::whereIn('id', $sourceIds->all())
                ->update(['merged_into_sales_order_id' => $mergedSalesOrder->id]);

            return $mergedSalesOrder;
        });
    }

    /**
     * Delete a merged Sales Order, its Delivery Order, and release the source Sales Orders
     * so they become available to be merged again.
     */
    public function deleteMergeResult(SalesOrder $salesOrder): void
    {
        DB::transaction(function () use ($salesOrder) {
            $deliveryOrderIds = $salesOrder->deliveryOrderDetails()
                ->pluck('delivery_order_id')
                ->unique()
                ->values();

            DeliveryOrder::whereIn('id', $deliveryOrderIds)
                ->lockForUpdate()
                ->get()
                ->each(fn (DeliveryOrder $deliveryOrder) => $this->deliveryOrderService->deleteDeliveryOrder($deliveryOrder));

            $salesOrder->mergedSalesOrders()->update(['merged_into_sales_order_id' => null]);

            $salesOrder->delete();
        });
    }

    /**
     * Validate that every given Sales Order can be merged together.
     *
     * @param  Collection<int, SalesOrder>  $sources
     */
    private function assertSourcesMergeable(Collection $sources): void
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

        $this->assertSourcesFullyVerified($sources);
    }

    /**
     * Ensure every source Sales Order has at least one detail and that every detail has been
     * fully scanned (verified). Merging is only allowed once all stock has been verified.
     *
     * @param  Collection<int, SalesOrder>  $sources
     */
    private function assertSourcesFullyVerified(Collection $sources): void
    {
        $notFullyVerified = $sources->filter(fn (SalesOrder $source) => $this->hasUnverifiedDetails($source));

        if ($notFullyVerified->isNotEmpty()) {
            throw new BadRequestHttpException(sprintf(
                'Sales Order belum selesai diverifikasi: %s. Semua item harus sudah discan sebelum di-merge.',
                $notFullyVerified->pluck('invoice_no')->implode(', ')
            ));
        }
    }

    /**
     * Whether the Sales Order has no details or at least one detail that is not fully verified.
     */
    private function hasUnverifiedDetails(SalesOrder $salesOrder): bool
    {
        return $salesOrder->details()->doesntExist()
            || $salesOrder->details()->whereColumn('fulfilled_qty', '<', 'qty')->exists();
    }

    /**
     * Combine the source details into order items, grouping identical product units and prices.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildMergedOrderItems(array $salesOrderIds): array
    {
        return SalesOrderDetail::whereIn('sales_order_id', $salesOrderIds)
            ->get()
            ->groupBy(fn (SalesOrderDetail $detail) => $this->detailGroupKey($detail))
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

    /**
     * Grouping key that identifies a merged detail across the source details.
     */
    private function detailGroupKey(SalesOrderDetail $detail): string
    {
        return implode('|', [
            $detail->product_unit_id,
            $detail->unit_price,
            $detail->discount,
            $detail->tax,
        ]);
    }

    /**
     * Mirror the verified (scanned) stock items of the source Sales Orders into the merged
     * Sales Order and its Delivery Order, so the merged order becomes an exact record of
     * what has actually been delivered.
     *
     * @param  array<int, int>  $sourceIds
     */
    private function copyVerifiedStockItems(SalesOrder $mergedSalesOrder, DeliveryOrder $deliveryOrder, array $sourceIds): void
    {
        $mergedDetails = SalesOrderDetail::where('sales_order_id', $mergedSalesOrder->id)
            ->get()
            ->keyBy(fn (SalesOrderDetail $detail) => $this->detailGroupKey($detail));

        $deliveryOrderDetailIds = DeliveryOrderDetail::where('delivery_order_id', $deliveryOrder->id)
            ->pluck('id', 'sales_order_detail_id');

        $sourceDetails = SalesOrderDetail::whereIn('sales_order_id', $sourceIds)
            ->with([
                'salesOrderItems' => fn ($query) => $query->where('is_returned', false)->orderByDesc('is_parent'),
            ])
            ->get();

        foreach ($sourceDetails as $sourceDetail) {
            $mergedDetail = $mergedDetails->get($this->detailGroupKey($sourceDetail));

            if (! $mergedDetail) {
                continue;
            }

            $deliveryOrderDetailId = $deliveryOrderDetailIds->get($mergedDetail->id);
            $items = $sourceDetail->salesOrderItems;

            foreach ($items->whereNull('parent_id') as $item) {
                $newItem = $mergedDetail->salesOrderItems()->create([
                    'stock_id' => $item->stock_id,
                    'is_parent' => $item->is_parent,
                    'is_returned' => false,
                    'delivery_order_detail_id' => $deliveryOrderDetailId,
                ]);

                foreach ($items->where('parent_id', $item->id) as $child) {
                    $mergedDetail->salesOrderItems()->create([
                        'stock_id' => $child->stock_id,
                        'is_parent' => false,
                        'parent_id' => $newItem->id,
                        'is_returned' => false,
                        'delivery_order_detail_id' => $deliveryOrderDetailId,
                    ]);
                }
            }

            $this->repository->countFulfilledQty($mergedDetail);
        }
    }

    public function findScanableMarketplaceOrder(int $id): SalesOrder
    {
        $salesOrder = SalesOrder::whereNotNull('marketplace')->findTenanted($id);

        if ($salesOrder->isMergeResult() || $salesOrder->isMergeSalesOrder()) {
            abort(Response::HTTP_BAD_REQUEST, 'Sales Order merge tidak dapat diverifikasi.');
        }

        if ($salesOrder->deliveryOrders()->exists()) {
            abort(Response::HTTP_BAD_REQUEST, 'Sales Order sudah memiliki Delivery Order dan tidak dapat diverifikasi.');
        }

        return $salesOrder;
    }

    public function findInvoice(int|string $id): SalesOrder
    {
        return SalesOrder::whereInvoice()->findTenanted($id);
    }

    public function paginateItems(SalesOrderDetail $salesOrderDetail, int $perPage): LengthAwarePaginator
    {
        return QueryBuilder::for(
            SalesOrderItem::query()
                ->where('sales_order_detail_id', $salesOrderDetail->id)
                ->select(SalesOrderItem::SELECT_COLUMNS)
                ->with('stock')
        )
            ->allowedFilters([
                'is_returned',
                'is_parent',
            ])
            ->paginate($perPage);
    }

    /**
     * Merge sources/results are read only, and a non-marketplace order cannot be
     * edited once a delivery order references it.
     */
    public function ensureEditable(SalesOrder $salesOrder): void
    {
        if ($salesOrder->isMergeSalesOrder()) {
            throw new ApiResponseException('Sales Order sumber merge tidak dapat diubah.', 400);
        }

        if ($salesOrder->isMergeResult()) {
            throw new ApiResponseException('Sales Order hasil merge tidak dapat diubah. Hapus Sales Order hasil merge untuk membatalkan merge.', 400);
        }

        if (
            ! $salesOrder->marketplace
            && SalesOrderDetail::where('sales_order_id', $salesOrder->id)->whereHas('deliveryOrderDetails')->exists()
        ) {
            throw new ApiResponseException('DO harus dihapus terlebih dahulu sebelum mengedit SO', 400);
        }
    }

    public function destroySalesOrder(SalesOrder $salesOrder): void
    {
        if ($salesOrder->isMergeSalesOrder()) {
            throw new ApiResponseException('Sales Order sumber merge tidak dapat dihapus.', 400);
        }

        if ($salesOrder->isMergeResult()) {
            $this->deleteMergeResult($salesOrder);

            return;
        }

        if ($salesOrder->has_delivery_order) {
            throw new ApiResponseException('Tidak dapat menghapus SO yang sudah direlasikan dengan DO', 400);
        }

        DB::transaction(function () use ($salesOrder) {
            if (! $salesOrder->deliveryOrders()->exists()) {
                $this->releaseScannedStock($salesOrder);
            }

            $salesOrder->delete();
        });
    }

    public function details(int $salesOrderId): Collection
    {
        $salesOrder = $this->repository->findTenanted($salesOrderId, ['id']);

        $query = SalesOrderDetail::where('sales_order_id', $salesOrder->id)->with([
            'warehouse' => fn ($q) => $q->select('id', 'code', 'name'),
            'productUnit' => fn ($q) => $q->select('id', 'name'),
        ]);

        return QueryBuilder::for($query)
            ->allowedFilters([
                AllowedFilter::scope('has_delivery_order'),
            ])->get();
    }

    public function findDetail(int $salesOrderId, mixed $salesOrderDetailId): SalesOrderDetail
    {
        $salesOrder = $this->repository->findTenanted($salesOrderId, ['id']);
        $salesOrderDetail = $salesOrder->details()->where('id', $salesOrderDetailId)->firstOrFail();

        return $salesOrderDetail->load(['warehouse' => fn ($q) => $q->select('id', 'code', 'name')]);
    }

    public function scannedStockIds(SalesOrderDetail $salesOrderDetail): Collection
    {
        return DB::table('sales_order_items')->select('stock_id', 'created_at')->where('sales_order_detail_id', $salesOrderDetail->id)->get();
    }

    public function scanItem(SalesOrderDetail $salesOrderDetail, mixed $stockId): SalesOrderItem
    {
        $stock = Stock::findOrFail($stockId);

        $cek = $salesOrderDetail->salesOrderItems()->whereNotReturned()->where('stock_id', $stock->id)->exists();
        if ($cek) {
            throw new ApiResponseException('Product sudah di scan', 400);
        }

        return $salesOrderDetail->salesOrderItems()->updateOrCreate(['stock_id' => $stock->id], ['is_returned' => false]);
    }

    public function unscanItem(SalesOrderDetail $salesOrderDetail, mixed $stockId): void
    {
        DB::beginTransaction();
        try {
            $salesOrderItem = $salesOrderDetail->salesOrderItems()->where('stock_id', $stockId)->first(['id']);
            $salesOrderDetail->salesOrderItems()
                ->where(fn ($q) => $q->where('id', $salesOrderItem->id)->orWhere('parent_id', $salesOrderItem->id))
                ->delete();

            $this->repository->countFulfilledQty($salesOrderDetail);
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            throw new ApiResponseException($th->getMessage(), 500);
        }
    }

    /**
     * stocks_count is used by the SO form (product picker counter), keep the count.
     */
    public function paginateProductUnitsNew(mixed $warehouseId, int $perPage): LengthAwarePaginator
    {
        $stockProductUnitQuery = fn ($q) => $q->select('id', 'warehouse_id', 'product_unit_id')->has('productUnit')->has('warehouse')
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->withCount(['stocks' => fn ($q) => $q->whereCountableStock()])
            ->with([
                'warehouse' => fn ($q) => $q->select('id', 'code'),
            ]);

        $query = ProductUnit::select('id', 'refer_id', 'uom_id', 'name', 'price', 'is_ppn', 'code')
            ->where('is_generate_qr', true)
            ->with([
                'refer' => fn ($q) => $q->select('id', 'name')->with('stockProductUnit', $stockProductUnitQuery),
                'uom' => fn ($q) => $q->select('id', 'name'),
            ])
            ->with('stockProductUnit', $stockProductUnitQuery)
            ->orderBy('id');

        return QueryBuilder::for($query)
            ->allowedFilters([
                AllowedFilter::callback('warehouse_id', fn ($q) => $q),
                AllowedFilter::scope('product_unit', 'search'),
                AllowedFilter::scope('company', 'whereCompany'),
            ])
            ->paginate($perPage)
            ->through(function (ProductUnit $productUnit) {
                $productUnit->price_discount = $productUnit->price;
                $productUnit->discount = 0;
                $productUnit->is_percentage = 1;

                if ($productUnit->refer && ! $productUnit->stockProductUnit) {
                    $productUnit->name = $productUnit->name.' - '.($productUnit->refer->stockProductUnit?->warehouse?->code ?? '');
                    $productUnit->setRelation('stockProductUnit', $productUnit->refer->stockProductUnit);
                } else {
                    $productUnit->name = $productUnit->name.' - '.($productUnit->stockProductUnit?->warehouse?->code ?? '');
                }

                $productUnit->unsetRelation('refer');

                return $productUnit;
            });
    }

    public function paginateProductUnits(int $perPage): LengthAwarePaginator
    {
        $query = StockProductUnit::select('id', 'warehouse_id', 'product_unit_id')->has('productUnit')->has('warehouse')
            ->withCount(['stocks' => fn ($q) => $q->whereAvailableStock()->whereNull('description')])
            ->with([
                'warehouse' => fn ($q) => $q->select('id', 'code'),
                'productUnit' => function ($q) {
                    $q->select('id', 'uom_id', 'product_id', 'name', 'price', 'is_ppn')
                        ->with([
                            'uom' => fn ($q) => $q->select('id', 'name'),
                            'product' => fn ($q) => $q->select('id', 'name', 'product_brand_id'),
                        ]);
                },
            ]);

        return QueryBuilder::for($query)
            ->allowedFilters([
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::scope('product_unit'),
                AllowedFilter::scope('company', 'whereCompany'),
            ])
            ->paginate($perPage)
            ->through(function ($stockProductUnit) {
                $productUnit = $stockProductUnit->productUnit;
                $productUnit->name = $productUnit->name.' - '.($stockProductUnit->warehouse?->code ?? '');
                $productUnit->price_discount = $productUnit->price;
                $productUnit->discount = 0;
                $productUnit->is_percentage = 1;

                return $stockProductUnit;
            });
    }
}
