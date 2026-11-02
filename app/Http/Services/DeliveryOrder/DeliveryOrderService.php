<?php

namespace App\Http\Services\DeliveryOrder;

use App\Enums\CompanyEnum;
use App\Exceptions\ApiResponseException;
use App\Helpers\ContentDispositionHelper;
use App\Http\Services\BaseService;
use App\Interfaces\Repositories\AdjustmentRequest\AdjustmentRequestRepositoryInterface;
use App\Interfaces\Repositories\DeliveryOrder\DeliveryOrderRepositoryInterface;
use App\Interfaces\Repositories\SalesOrder\SalesOrderRepositoryInterface;
use App\Interfaces\Repositories\StockHistory\StockHistoryRepositoryInterface;
use App\Interfaces\Services\DeliveryOrder\DeliveryOrderServiceInterface;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderDetail;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use App\Models\SalesOrderItem;
use App\Models\Stock;
use App\Models\StockProductUnit;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class DeliveryOrderService extends BaseService implements DeliveryOrderServiceInterface
{
    public function __construct(
        protected DeliveryOrderRepositoryInterface $repository,
        protected SalesOrderRepositoryInterface $salesOrderRepository,
        protected StockHistoryRepositoryInterface $stockHistoryRepository,
        protected AdjustmentRequestRepositoryInterface $adjustmentRequestRepository,
    ) {
        parent::__construct($repository);
    }

    public function paginateIndex(int $perPage): LengthAwarePaginator
    {
        return QueryBuilder::for(
            DeliveryOrder::tenanted()
                ->with('user', 'reseller')
                ->withCount('details')
                ->withCount('mergedSalesOrders')
        )
            ->allowedFilters([
                'invoice_no',
                AllowedFilter::scope('search'),
                AllowedFilter::exact('reseller_id'),
                AllowedFilter::exact('company'),
                AllowedFilter::exact('sales_order_id'),
                AllowedFilter::callback('is_marketplace_source', function ($q, $value) {
                    filter_var($value, FILTER_VALIDATE_BOOLEAN)
                        ? $q->whereNotNull('sales_order_id')
                        : $q->whereNull('sales_order_id');
                }),
                AllowedFilter::scope('start_date'),
                AllowedFilter::scope('end_date'),
            ])
            ->allowedSorts(['id', 'invoice_no', 'user_id', 'reseller_id', 'company', 'is_done', 'created_at'])
            ->paginate($perPage);
    }

    public function findForShow(int|string $id): DeliveryOrder
    {
        $deliveryOrder = $this->repository->findTenanted($id);
        $deliveryOrder->load([
            'user',
            'reseller',
            'mergedSalesOrders' => fn ($q) => $q->select('id', 'invoice_no', 'merged_into_sales_order_id'),
        ]);
        $deliveryOrder->loadCount('mergedSalesOrders');

        return $deliveryOrder;
    }

    public function findBySalesOrderDetail(mixed $salesOrderDetailId): DeliveryOrderDetail
    {
        return DeliveryOrderDetail::where('sales_order_detail_id', $salesOrderDetailId)->firstOrFail('delivery_order_id');
    }

    /**
     * Retried because the document number is reserved in the creating hook.
     */
    public function createDeliveryOrder(array $data): DeliveryOrder
    {
        return DB::transaction(
            fn () => $this->repository->create($data),
            3
        );
    }

    /**
     * Removes the scanned items of the DO, recounts fulfilled qty and, when the DO
     * was done, records the stock coming back.
     */
    public function destroyDeliveryOrder(DeliveryOrder $deliveryOrder): void
    {
        DB::beginTransaction();
        try {
            $deliveryOrderDetails = $deliveryOrder->details;

            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            SalesOrderItem::whereIn('delivery_order_detail_id', $deliveryOrderDetails->pluck('id'))->delete();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            $deliveryOrderDetails->each(fn ($d) => $this->salesOrderRepository->countFulfilledQty($d->sales_order_detail_id));

            if ($deliveryOrder->is_done) {
                $deliveryOrder->details?->each(function ($detail) use ($deliveryOrder) {
                    $salesOrderDetail = $detail->salesOrderDetail->load([
                        'productUnit' => fn ($q) => $q->select('id', 'refer_id', 'refer_qty')->with('relations', fn ($q) => $q->with('relatedProductUnit', fn ($q) => $q->select('id', 'is_generate_qr'))),
                    ]);

                    $stockProductUnit = $this->findStockProductUnitFor($salesOrderDetail);

                    if ($stockProductUnit) {
                        if ($salesOrderDetail?->fulfilled_qty > 0) {
                            $history = $this->stockHistoryRepository->record($stockProductUnit, [
                                'user_id' => auth('sanctum')->id(),
                                'stock_product_unit_id' => $stockProductUnit->id,
                                'value' => $salesOrderDetail?->fulfilled_qty ?? 0,
                                'is_increment' => 1,
                                'description' => $deliveryOrder->invoice_no.' - Delete DO',
                                'ip' => request()->ip(),
                                'agent' => request()->header('user-agent'),
                            ]);

                            $this->moveRelatedUnits($salesOrderDetail, $history, true);
                        }
                    }
                });
            }

            $deliveryOrder->delete();
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            throw new ApiResponseException($e->getMessage(), 500);
        }
    }

    public function print(int|string $id): mixed
    {
        $deliveryOrder = DeliveryOrder::with([
            'reseller' => fn ($q) => $q->select('id', 'name', 'address'),
            'details' => fn ($q) => $q->with('salesOrderDetail.productUnit.uom'),
        ])->findTenanted($id);

        $chunkSize = 19;
        $view = 'pdf.deliveryOrders.deliveryOrder';
        if ($deliveryOrder->company->is(CompanyEnum::PA)) {
            $chunkSize = 9;
            $view = 'pdf.deliveryOrders.deliveryOrderPA';
        }

        $deliveryOrderDetailsChunk = $deliveryOrder->details?->chunk($chunkSize) ?? collect([]);

        $pdf = Pdf::setPaper('a4', 'portrait')
            ->setOption('defaultFont', 'Arial')
            ->loadView($view, ['deliveryOrder' => $deliveryOrder, 'deliveryOrderDetailsChunk' => $deliveryOrderDetailsChunk]);

        return $pdf->download('delivery-order-'.$deliveryOrder->code.'.pdf');
    }

    public function exportXml(int|string $id): mixed
    {
        $productUnitColumns = ['id', 'uom_id', 'refer_id', 'refer_qty', 'code', 'name', 'price'];
        $uomColumns = 'uom:id,name';
        $deliveryOrder = DeliveryOrder::with([
            'reseller',
            'details' => fn ($q) => $q->with('salesOrderDetail', fn ($q) => $q->with(
                'productUnit',
                fn ($q) => $q->select($productUnitColumns)
                    ->with([
                        $uomColumns,
                        'refer' => fn ($q) => $q->select($productUnitColumns)->with($uomColumns),
                        'relations' => fn ($q) => $q->with('relatedProductUnit', fn ($q) => $q->select($productUnitColumns)->with($uomColumns)),
                    ])
            )),
        ])->findTenanted($id);

        $filename = ContentDispositionHelper::filename(
            $deliveryOrder->invoice_no,
            'delivery-order-'.$deliveryOrder->id
        );

        return response(view('xml.deliveryOrders.deliveryOrder')->with(compact('deliveryOrder')), 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => ContentDispositionHelper::attachmentHeader($filename),
        ]);
    }

    /**
     * Scans one stock into a DO detail. A group parent brings its children along and
     * replaces children of that group scanned on their own before.
     */
    public function verify(int|string $id, DeliveryOrderDetail $deliveryOrderDetail, mixed $stockId): SalesOrderItem
    {
        $deliveryOrder = $this->repository->findTenanted($id, ['id', 'is_done']);
        if ($deliveryOrder->is_done) {
            throw new ApiResponseException('Delivery Order sudah diselesaikan. Batalkan untuk dapat scan lagi', 404);
        }

        $salesOrderDetail = $deliveryOrderDetail->salesOrderDetail->load(['productUnit' => fn ($q) => $q->select('id', 'refer_id')]);

        $stock = Stock::where('id', $stockId)
            ->whereHas(
                'stockProductUnit',
                fn ($q) => $q->where('warehouse_id', $salesOrderDetail->salesOrder?->warehouse_id)
                    ->when(
                        $salesOrderDetail->productUnit->refer_id,
                        fn ($q) => $q->where('product_unit_id', $salesOrderDetail->productUnit->refer_id),
                        fn ($q) => $q->where('product_unit_id', $salesOrderDetail->product_unit_id),
                    )
            )
            ->first();

        if (! $stock) {
            throw new ApiResponseException('Stok produk tidak sesuai', 400);
        }

        $cek = SalesOrderItem::where('stock_id', $stock?->id)->whereNotReturned()->exists();
        if ($cek) {
            throw new ApiResponseException('Product sudah pernah di verifikasi', 400);
        }

        $fulfilledQty = $salesOrderDetail->salesOrderItems()->where('delivery_order_detail_id', $deliveryOrderDetail->id)->where('is_parent', 0)->count();
        if ($fulfilledQty >= $deliveryOrderDetail->qty) {
            throw new ApiResponseException('Qty sudah terpenuhi', 400);
        }

        $stock->load(['childs' => fn ($q) => $q->select('id', 'parent_id', 'stock_product_unit_id')]);
        $stockChilds = $stock->childs;
        $totalChilds = $stockChilds->count();

        if ($totalChilds > 0) {
            if ($stock->hasIncompleteGrouping()) {
                throw new ApiResponseException(Stock::INCOMPLETE_GROUPING_MESSAGE, 400);
            }

            $stockIds = $salesOrderDetail->salesOrderItems()
                ->where('delivery_order_detail_id', $deliveryOrderDetail->id)
                ->whereIn('stock_id', $stockChilds->pluck('id'))
                ->pluck('stock_id');

            if ($stockIds->count() > 0) {
                $totalStockScanned = $fulfilledQty + $totalChilds - $stockIds->count();
            } else {
                $totalStockScanned = $fulfilledQty + $totalChilds;
            }

            if ($totalStockScanned > $deliveryOrderDetail->qty) {
                throw new ApiResponseException(sprintf('Jumlah barang yang di scan (%d) melebihi qty. Tersisa %d lagi', $totalChilds - $stockIds->count(), $deliveryOrderDetail->qty - $fulfilledQty), 400);
            }

            $dataStocks = [];
            DB::beginTransaction();
            try {
                $salesOrderItem = $salesOrderDetail->salesOrderItems()->updateOrCreate(['stock_id' => $stock->id, 'delivery_order_detail_id' => $deliveryOrderDetail->id], ['is_returned' => false, 'is_parent' => true]);

                SalesOrderItem::where('sales_order_detail_id', $salesOrderDetail->id)->where('delivery_order_detail_id', $deliveryOrderDetail->id)->whereIn('stock_id', $stockIds)->delete();

                foreach ($stock->childs as $child) {
                    $dataStocks[] = ['stock_id' => $child->id, 'sales_order_detail_id' => $salesOrderDetail->id, 'delivery_order_detail_id' => $deliveryOrderDetail->id, 'is_returned' => false];
                }

                $salesOrderItem->childs()->createMany($dataStocks);

                $this->salesOrderRepository->countFulfilledQty($salesOrderDetail);
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();

                throw new ApiResponseException($e->getMessage(), 500);
            }
        } else {
            DB::beginTransaction();
            try {
                $salesOrderItem = $salesOrderDetail->salesOrderItems()->updateOrCreate(['stock_id' => $stock->id, 'delivery_order_detail_id' => $deliveryOrderDetail->id], ['is_returned' => false]);

                $this->salesOrderRepository->countFulfilledQty($salesOrderDetail);
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();

                throw new ApiResponseException($e->getMessage(), 500);
            }
        }

        return $salesOrderItem;
    }

    /**
     * Done records the stock leaving (and decrements non-QR bundle components);
     * undone records it coming back.
     */
    public function setDone(int|string $id, mixed $isDone): DeliveryOrder
    {
        $deliveryOrder = $this->repository->findTenanted($id, ['id', 'is_done', 'invoice_no']);
        if ($isDone == $deliveryOrder->is_done) {
            throw new ApiResponseException('Tidak dapat update status yang sama.', 400);
        }

        DB::beginTransaction();
        try {
            $deliveryOrder->update([
                'is_done' => $isDone,
                'done_at' => now(),
            ]);

            $isIncrement = $deliveryOrder->is_done ? 0 : 1;
            $suffix = $deliveryOrder->is_done ? ' - Verified DO' : ' - Unverified DO';

            $deliveryOrder->details?->each(function ($detail) use ($deliveryOrder, $isIncrement, $suffix) {
                $salesOrderDetail = $detail->salesOrderDetail->load([
                    'productUnit' => fn ($q) => $q->select('id', 'refer_id', 'refer_qty')->with('relations', fn ($q) => $q->with('relatedProductUnit', fn ($q) => $q->select('id', 'is_generate_qr'))),
                ]);

                $stockProductUnit = $this->findStockProductUnitFor($salesOrderDetail);

                if ($stockProductUnit) {
                    $history = $this->stockHistoryRepository->record($salesOrderDetail, [
                        'user_id' => auth('sanctum')->id(),
                        'stock_product_unit_id' => $stockProductUnit->id,
                        'value' => $salesOrderDetail?->fulfilled_qty ?? 0,
                        'is_increment' => $isIncrement,
                        'description' => $deliveryOrder->invoice_no.$suffix,
                        'ip' => request()->ip(),
                        'agent' => request()->header('user-agent'),
                    ]);

                    $this->moveRelatedUnits($salesOrderDetail, $history, (bool) $isIncrement);
                }
            });

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            throw new ApiResponseException($e->getMessage(), 500);
        }

        return $deliveryOrder;
    }

    /**
     * Known issue kept as is: the guards hand back the exception instead of throwing
     * it, so the endpoint answers 200 with the exception text.
     */
    public function returnStocks(int|string $id, mixed $ids, mixed $isDelete): ?BadRequestHttpException
    {
        $userId = auth()->id();
        $ip = request()->ip();
        $userAgent = request()->header('User-Agent');

        $salesOrderDetailIds = SalesOrderItem::whereIn('stock_id', $ids)
            ->whereNotReturned()
            ->distinct()
            ->pluck('sales_order_detail_id')
            ->toArray();

        if (count($salesOrderDetailIds) == 0) {
            return new BadRequestHttpException('Stock not found');
        }

        $deliveryOrderDetails = DeliveryOrderDetail::whereIn('sales_order_detail_id', $salesOrderDetailIds)->distinct()->pluck('delivery_order_id')->toArray();
        if (count($deliveryOrderDetails) > 1 || $deliveryOrderDetails[0] != $id) {
            return new BadRequestHttpException('Can not return stock from different delivery order');
        }

        $invoiceNo = DeliveryOrder::select('invoice_no')->findOrFail($id)->invoice_no;

        DB::transaction(function () use ($ids, $isDelete, $invoiceNo, $salesOrderDetailIds, $userId, $ip, $userAgent) {
            SalesOrderItem::whereIn('stock_id', $ids)->orWhereHas('stock', fn ($q) => $q->whereIn('parent_id', $ids))->update(['is_returned' => true]);

            foreach ($salesOrderDetailIds as $id) {
                $this->salesOrderRepository->countFulfilledQty($id);
            }

            if ($isDelete == true) {
                Stock::whereIn('id', $ids)->orWhereIn('parent_id', $ids)->delete();
            } else {
                $stockCounts = Stock::whereIn('id', $ids)
                    ->orWhereIn('parent_id', $ids)
                    ->where(
                        fn ($q) => $q->whereNotNull('parent_id')
                            ->orWhere(fn ($q) => $q->whereNull('parent_id')->doesntHave('childs'))
                    )
                    ->select('stock_product_unit_id', DB::raw('count(*) as qty'))
                    ->groupBy('stock_product_unit_id')
                    ->get();

                foreach ($stockCounts as $stockCount) {
                    $stockProductUnitId = $stockCount->stock_product_unit_id;
                    $qty = $stockCount->qty - 1;
                    $stockProductUnit = StockProductUnit::where('id', $stockProductUnitId)->with('productUnit', fn ($q) => $q->select('id', 'name', 'is_generate_qr'))->first();

                    $adjustmentRequest = $this->adjustmentRequestRepository->create([
                        'user_id' => $userId,
                        'approved_by' => $userId,
                        'stock_product_unit_id' => $stockProductUnitId,
                        'value' => $qty,
                        'is_increment' => 1,
                        'is_approved' => 1,
                        'description' => sprintf(
                            'Return from DO %s - %s',
                            $invoiceNo,
                            $stockProductUnit->productUnit?->name ?? '',
                        ),
                    ]);

                    $this->stockHistoryRepository->record($adjustmentRequest, [
                        'user_id' => $userId,
                        'stock_product_unit_id' => $stockProductUnitId,
                        'value' => $qty,
                        'is_increment' => 1,
                        'description' => $adjustmentRequest->description,
                        'ip' => $ip,
                        'agent' => $userAgent,
                    ]);

                    if (! $stockProductUnit->productUnit->is_generate_qr) {
                        $stockProductUnit->increment('qty', $qty);
                    }
                }
            }
        });

        return null;
    }

    private function findStockProductUnitFor(SalesOrderDetail $salesOrderDetail): ?StockProductUnit
    {
        return StockProductUnit::tenanted()->where('warehouse_id', $salesOrderDetail?->warehouse_id)
            ->when(
                $salesOrderDetail->productUnit->refer_id,
                fn ($q) => $q->where('product_unit_id', $salesOrderDetail->productUnit->refer_id),
                fn ($q) => $q->where('product_unit_id', $salesOrderDetail->product_unit_id),
            )
            ->first(['id']);
    }

    /**
     * Bundle units also move their non-QR components (e.g. packaging), copying the
     * parent history row.
     */
    private function moveRelatedUnits(SalesOrderDetail $salesOrderDetail, $history, bool $increment): void
    {
        if ($salesOrderDetail->productUnit->refer_id && $salesOrderDetail->productUnit->relations->count()) {
            $multiply = floor($salesOrderDetail->qty / $salesOrderDetail->productUnit->refer_qty);
            foreach ($salesOrderDetail->productUnit->relations as $relation) {
                $stockProductUnit = StockProductUnit::where('product_unit_id', $relation->related_product_unit_id)->where('warehouse_id', $salesOrderDetail?->warehouse_id)->first();
                if ($stockProductUnit) {
                    $qty = $relation->qty * $multiply;
                    if (! $relation->relatedProductUnit->is_generate_qr) {
                        $increment ? $stockProductUnit->increment('qty', $qty) : $stockProductUnit->decrement('qty', $qty);
                    }

                    $this->stockHistoryRepository->record($stockProductUnit, [
                        'user_id' => $history->user_id,
                        'stock_product_unit_id' => $stockProductUnit->id,
                        'value' => $qty,
                        'is_increment' => $history->is_increment,
                        'description' => $history->description,
                        'ip' => $history->ip,
                        'agent' => $history->agent,
                    ]);
                }
            }
        }
    }

    /**
     * Create a Delivery Order for a marketplace Sales Order and link every SO detail.
     *
     * No-op (null) when the Sales Order is not a marketplace order, unless $force is true.
     * $invoiceNo fills the document number when the counter is disabled; null lets the
     * counter generate it.
     */
    public function createForSalesOrder(SalesOrder $salesOrder, bool $force = false, ?string $invoiceNo = null): ?DeliveryOrder
    {
        if (! $force && ! $salesOrder->marketplace) {
            return null;
        }

        $deliveryOrder = $this->repository->create([
            'warehouse_id' => $salesOrder->warehouse_id,
            'reseller_id' => $salesOrder->reseller_id,
            'sales_order_id' => $salesOrder->id,
            'company' => $salesOrder->company ?? CompanyEnum::PAS,
            'invoice_no' => $invoiceNo,
            'transaction_date' => $salesOrder->transaction_date ?? now(),
            'shipment_estimation_datetime' => $salesOrder->shipment_estimation_datetime ?? now(),
            'description' => $salesOrder->description,
        ]);

        $this->attachSalesOrderDetails($deliveryOrder, $salesOrder->details);

        return $deliveryOrder;
    }

    /**
     * Validate that the Sales Order's referencing Delivery Order can be auto-synced.
     *
     * @return DeliveryOrder|null the single referencing Delivery Order, null when none
     */
    public function assertEditable(SalesOrder $salesOrder): ?DeliveryOrder
    {
        $detailIds = SalesOrderDetail::where('sales_order_id', $salesOrder->id)->pluck('id');

        $deliveryOrderIds = DeliveryOrderDetail::whereIn('sales_order_detail_id', $detailIds)
            ->distinct()
            ->pluck('delivery_order_id');

        if ($deliveryOrderIds->isEmpty()) {
            return null;
        }

        if ($deliveryOrderIds->count() > 1) {
            throw new BadRequestHttpException('Sales Order direlasikan dengan lebih dari 1 Delivery Order. Hapus Delivery Order terlebih dahulu sebelum mengedit Sales Order.');
        }

        $deliveryOrder = DeliveryOrder::findOrFail($deliveryOrderIds->first());

        if ($deliveryOrder->is_done) {
            throw new BadRequestHttpException('Delivery Order sudah diselesaikan. Batalkan status Done terlebih dahulu sebelum mengedit Sales Order.');
        }

        if ($deliveryOrder->details()->whereNotIn('sales_order_detail_id', $detailIds)->exists()) {
            throw new BadRequestHttpException('Delivery Order berisi Sales Order lain. Lepas terlebih dahulu sebelum mengedit Sales Order.');
        }

        return $deliveryOrder;
    }

    /**
     * Sales Order detail IDs that already have a verified (non-returned) stock item.
     * These details are locked during an edit; untouched details stay editable.
     *
     * @return Collection<int, int>
     */
    public function lockedSalesOrderDetailIds(SalesOrder $salesOrder): Collection
    {
        $detailIds = SalesOrderDetail::where('sales_order_id', $salesOrder->id)->pluck('id');

        return SalesOrderItem::whereIn('sales_order_detail_id', $detailIds)
            ->whereNotNull('stock_id')
            ->whereNotReturned()
            ->distinct()
            ->pluck('sales_order_detail_id');
    }

    /**
     * Keep verified (scanned) Sales Order details immutable while editing the order.
     *
     * The request payload is validated against the stored values and the stored values are
     * written back into raw_source, so the pricing pipeline always recomputes from what is
     * actually persisted. This prevents both silent price drift and removal of scanned data.
     */
    public function normalizeLockedItems(SalesOrder $salesOrder): void
    {
        if (! $salesOrder->exists || $salesOrder->getRawOriginal('marketplace') === null) {
            return;
        }

        $lockedDetails = $this->lockedDetailsByKey($salesOrder);

        if ($lockedDetails->isEmpty()) {
            return;
        }

        if (! $salesOrder->marketplace) {
            throw new BadRequestHttpException(self::VERIFIED_STOCK_MESSAGE);
        }

        $rawSource = $salesOrder->raw_source;
        $items = collect($rawSource['items'] ?? []);

        foreach ($lockedDetails as $key => $detail) {
            $item = $items->first(fn ($item) => $this->itemKey($item) === $key);

            if (! $item || $this->itemChanged($item, $detail)) {
                throw new BadRequestHttpException(self::VERIFIED_STOCK_MESSAGE);
            }
        }

        $rawSource['items'] = $items->map(function ($item) use ($lockedDetails) {
            $detail = $lockedDetails->get($this->itemKey($item));

            if ($detail) {
                $item['qty'] = (int) $detail->qty;
                $item['unit_price'] = (int) $detail->unit_price;
                $item['discount'] = (int) $detail->discount;
                $item['tax'] = (int) $detail->tax === 11 ? 1 : 0;
                $item['total_price'] = (int) $detail->total_price;
            }

            return $item;
        })->all();

        $salesOrder->raw_source = $rawSource;
    }

    /**
     * @return Collection<string, SalesOrderDetail> keyed by product_unit_id|warehouse_id
     */
    private function lockedDetailsByKey(SalesOrder $salesOrder): Collection
    {
        $lockedIds = $this->lockedSalesOrderDetailIds($salesOrder);

        if ($lockedIds->isEmpty()) {
            return collect();
        }

        return SalesOrderDetail::whereIn('id', $lockedIds)
            ->get()
            ->keyBy(fn (SalesOrderDetail $detail) => $this->detailKey($detail));
    }

    private function detailKey(SalesOrderDetail $detail): string
    {
        return $detail->product_unit_id.'|'.($detail->warehouse_id ?? '');
    }

    private function itemKey(array $item): string
    {
        return ($item['product_unit_id'] ?? '').'|'.($item['warehouse_id'] ?? '');
    }

    private function itemChanged(array $item, SalesOrderDetail $detail): bool
    {
        $itemTax = ! empty($item['tax']) ? 11 : 0;

        return (int) ($item['qty'] ?? 0) !== (int) $detail->qty
            || (int) ($item['unit_price'] ?? 0) !== (int) $detail->unit_price
            || (int) ($item['discount'] ?? 0) !== (int) $detail->discount
            || $itemTax !== (int) $detail->tax;
    }

    /**
     * Link Sales Order details into the Delivery Order (qty upsert, remaining-qty safe).
     *
     * @param  iterable<int, array{sales_order_detail_id: int, qty: int}>  $details
     * @return array{count: int, errors: array<int, array{sales_order_detail_id: int|string, message: string}>}
     */
    public function attachDetails(DeliveryOrder $deliveryOrder, iterable $details): array
    {
        $count = 0;
        $errors = [];

        foreach ($details as $item) {
            $salesOrderDetail = SalesOrderDetail::find($item['sales_order_detail_id']);

            if (! $salesOrderDetail) {
                $errors[] = [
                    'sales_order_detail_id' => $item['sales_order_detail_id'],
                    'message' => 'Sales order detail tidak ditemukan.',
                ];

                continue;
            }

            $alreadyScheduled = DeliveryOrderDetail::where('sales_order_detail_id', $salesOrderDetail->id)
                ->where('delivery_order_id', '!=', $deliveryOrder->id)
                ->sum('qty');

            $remaining = $salesOrderDetail->qty - $alreadyScheduled;

            if ($item['qty'] > $remaining) {
                $errors[] = [
                    'sales_order_detail_id' => $salesOrderDetail->id,
                    'message' => "Qty melebihi sisa yang tersedia. Sisa: {$remaining}, diminta: {$item['qty']}",
                ];

                continue;
            }

            $existing = $deliveryOrder->details()
                ->where('sales_order_detail_id', $salesOrderDetail->id)
                ->first();

            if ($existing) {
                $existing->update(['qty' => $item['qty']]);
            } else {
                $deliveryOrder->details()->create([
                    'sales_order_detail_id' => $salesOrderDetail->id,
                    'qty' => $item['qty'],
                ]);

                $count++;
            }
        }

        return ['count' => $count, 'errors' => $errors];
    }

    /**
     * Link all given Sales Order details into the Delivery Order or fail the transaction.
     *
     * @param  Collection<int, SalesOrderDetail>  $details
     */
    public function attachSalesOrderDetails(DeliveryOrder $deliveryOrder, Collection $details): void
    {
        $result = $this->attachDetails($deliveryOrder, $details->map(fn (SalesOrderDetail $detail) => [
            'sales_order_detail_id' => $detail->id,
            'qty' => $detail->qty,
        ]));

        if (! empty($result['errors'])) {
            throw new BadRequestHttpException($result['errors'][0]['message']);
        }
    }

    /**
     * Keep the Delivery Order header in sync with its Sales Order.
     */
    public function syncHeader(DeliveryOrder $deliveryOrder, SalesOrder $salesOrder): void
    {
        $deliveryOrder->update([
            'warehouse_id' => $salesOrder->warehouse_id,
            'reseller_id' => $salesOrder->reseller_id,
            'company' => $salesOrder->company ?? CompanyEnum::PAS,
            'transaction_date' => $salesOrder->transaction_date ?? $deliveryOrder->transaction_date,
            'shipment_estimation_datetime' => $salesOrder->shipment_estimation_datetime ?? $deliveryOrder->shipment_estimation_datetime,
            'description' => $salesOrder->description ?? $deliveryOrder->description,
        ]);
    }

    /**
     * Delete a Delivery Order together with its sales_order_items references.
     */
    public function deleteDeliveryOrder(DeliveryOrder $deliveryOrder): void
    {
        $deliveryOrderDetailIds = $deliveryOrder->details()->pluck('id');

        SalesOrderItem::whereIn('delivery_order_detail_id', $deliveryOrderDetailIds)->delete();
        $deliveryOrder->delete();
    }
}
