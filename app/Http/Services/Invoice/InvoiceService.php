<?php

namespace App\Http\Services\Invoice;

use App\Exceptions\ApiResponseException;
use App\Exports\InvoiceExport;
use App\Interfaces\Repositories\StockHistory\StockHistoryRepositoryInterface;
use App\Interfaces\Repositories\User\UserRepositoryInterface;
use App\Interfaces\Repositories\Warehouse\WarehouseRepositoryInterface;
use App\Interfaces\Services\Invoice\InvoiceNumberServiceInterface;
use App\Interfaces\Services\Invoice\InvoiceServiceInterface;
use App\Interfaces\Services\SalesOrder\SalesOrderServiceInterface;
use App\Models\SalesOrder;
use App\Models\Stock;
use App\Models\StockProductUnit;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InvoiceService implements InvoiceServiceInterface
{
    public function __construct(
        protected SalesOrderServiceInterface $salesOrderService,
        protected InvoiceNumberServiceInterface $invoiceNumberService,
        protected StockHistoryRepositoryInterface $stockHistoryRepository,
        protected WarehouseRepositoryInterface $warehouseRepository,
        protected UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * Items sent with stock_ids must match available stock of their product unit;
     * other items need qty available stock. Nothing is reserved here.
     */
    public function ensureStockAvailable(mixed $items): void
    {
        foreach ($items ?? [] as $item) {
            if (! empty($item['stock_ids'])) {
                $error = $this->validateScannedStocks($item);
                if ($error) {
                    throw new ApiResponseException($error, Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                continue;
            }

            $stocks = Stock::whereAvailableStock()
                ->whereNull('description')
                ->whereHas('stockProductUnit', fn ($q) => $q->where('product_unit_id', $item['product_unit_id'])->where('warehouse_id', $item['warehouse_id']))
                ->limit($item['qty'])
                ->get(['id']);

            if ($stocks->count() < $item['qty']) {
                throw new ApiResponseException(sprintf('Stok %s tidak tersedia', DB::table('product_units')->where('id', $item['product_unit_id'])->first()?->name ?? ''), Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }
    }

    public function createInvoice(mixed $data, mixed $isPreview): SalesOrder
    {
        $salesOrder = $this->salesOrderService->createOrder(SalesOrder::make(['raw_source' => $data, 'is_invoice' => true]), $isPreview);

        return $salesOrder->loadMissing('vouchers.category');
    }

    public function findForShow(mixed $id): SalesOrder
    {
        $salesOrder = $this->salesOrderService->show($id, fn ($q) => $q->whereInvoice());
        $salesOrder->id_hash = Crypt::encryptString($salesOrder->id);
        $salesOrder->whatsapp_url = empty($salesOrder->invoice_no) ? '' : $this->salesOrderService->getWhatsappUrl($salesOrder, $salesOrder->id_hash);

        return $salesOrder;
    }

    public function ensureUpdatable(SalesOrder $salesOrder): void
    {
        if ($salesOrder->payment_status == 'paid') {
            throw new ApiResponseException('Invoice sudah lunas tidak dapat diupdate', 400);
        }

        if (empty($salesOrder->invoice_no)) {
            throw new ApiResponseException('Konversi ke invoice terlebih dahulu untuk dapat mengedit.', 400);
        }
    }

    /**
     * Known issue kept as is: the deliveryOrder relation no longer exists, so this
     * guard never fires.
     */
    public function ensureDeletable(SalesOrder $salesOrder): void
    {
        if ($salesOrder->deliveryOrder?->is_done) {
            throw new ApiResponseException("Can't update SO if DO is already done", 400);
        }
    }

    public function destroyInvoice(SalesOrder $salesOrder): void
    {
        DB::beginTransaction();
        try {
            $salesOrder->details->each(function ($salesOrderDetail) use ($salesOrder) {
                $stockProductUnit = StockProductUnit::where('warehouse_id', $salesOrderDetail->warehouse_id)
                    ->where('product_unit_id', $salesOrderDetail->product_unit_id)
                    ->first(['id']);

                $this->stockHistoryRepository->record($salesOrderDetail, [
                    'user_id' => $salesOrder->user_id,
                    'stock_product_unit_id' => $stockProductUnit->id,
                    'value' => $salesOrderDetail->qty,
                    'is_increment' => 1,
                    'description' => 'Return stock from delete SO invoice '.$salesOrder->invoice_no,
                    'ip' => request()->ip(),
                    'agent' => request()->header('user-agent'),
                ]);
            });

            $salesOrder->details->each(fn ($salesOrderDetail) => $salesOrderDetail->salesOrderItems()->delete());

            $salesOrder->forceDelete();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            throw $e;
        }
    }

    public function invoiceNumberFor(mixed $warehouseId): string
    {
        $warehouse = $this->warehouseRepository->query()->findOrFail($warehouseId, ['id', 'code']);

        return $this->invoiceNumberService->getSoNumber($warehouse);
    }

    public function export(): BinaryFileResponse
    {
        return Excel::download(new InvoiceExport, 'invoices.xlsx');
    }

    public function cashiers(): Collection
    {
        return cache()->remember('cashiers', 60 * 60, function () {
            return $this->userRepository->query()->whereIn('id', config('app.cashier_ids'))->get(['id', 'name']);
        });
    }

    private function validateScannedStocks(mixed $item): ?string
    {
        $stockProductUnit = StockProductUnit::where('product_unit_id', $item['product_unit_id'])
            ->where('warehouse_id', $item['warehouse_id'])
            ->first(['id']);

        if (! $stockProductUnit) {
            return 'Stok produk tidak sesuai';
        }

        $matchedCount = Stock::whereIn('id', $item['stock_ids'])
            ->where('stock_product_unit_id', $stockProductUnit->id)
            ->whereDoesntHave('salesOrderItems', fn ($q) => $q->whereNotReturned())
            ->count();

        if ($matchedCount !== count($item['stock_ids'])) {
            return 'Stok produk tidak sesuai';
        }

        return null;
    }
}
