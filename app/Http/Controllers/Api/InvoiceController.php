<?php

namespace App\Http\Controllers\Api;

use App\Exports\InvoiceExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\InvoiceStoreRequest;
use App\Http\Requests\Api\InvoiceUpdateRequest;
use App\Http\Requests\Api\SalesOrderItemStoreRequest;
use App\Http\Resources\DefaultResource;
use App\Http\Resources\SalesOrderItemResource;
use App\Http\Resources\SalesOrderResource;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use App\Models\SalesOrderItem;
use App\Models\Stock;
use App\Models\StockProductUnit;
use App\Models\Warehouse;
use App\Services\SalesOrderService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\QueryBuilder\QueryBuilder;

class InvoiceController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->middleware('permission:invoice_read', ['only' => ['index', 'show', 'items']]);
        $this->middleware('permission:invoice_create', ['only' => ['store', 'verification']]);
        // $this->middleware('permission:invoice_edit', ['only' => 'update']);
        $this->middleware('permission:invoice_delete', ['only' => 'destroy']);
        // $this->middleware('permission:invoice_print', ['only' => 'print']);
        $this->middleware('permission:invoice_export_xml', ['only' => 'exportXml']);
    }

    public function index()
    {
        return SalesOrderService::index($this->per_page, fn($q) => $q->where('is_invoice', true));
    }

    public function show($id)
    {
        $salesOrder = SalesOrderService::show($id, fn($q) => $q->where('is_invoice', true));
        $salesOrder->id_hash = Crypt::encryptString($salesOrder->id);
        $salesOrder->whatsapp_url = empty($salesOrder->invoice_no) ? '' : SalesOrderService::getWhatsappUrl($salesOrder, $salesOrder->id_hash);

        return new SalesOrderResource($salesOrder);
    }

    public function store(InvoiceStoreRequest $request)
    {
        foreach ($request->items ?? [] as $item) {
            if (! empty($item['stock_ids'])) {
                $error = $this->validateScannedStocks($item);
                if ($error) {
                    return $this->errorResponse(message: $error, code: \Illuminate\Http\Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                continue;
            }

            $stocks = \App\Models\Stock::whereAvailableStock()
                ->whereNull('description')
                ->whereHas('stockProductUnit', fn($q) => $q->where('product_unit_id', $item['product_unit_id'])->where('warehouse_id', $item['warehouse_id']))
                ->limit($item['qty'])
                ->get(['id']);

            if ($stocks->count() < $item['qty']) {
                return $this->errorResponse(message: sprintf('Stok %s tidak tersedia', \Illuminate\Support\Facades\DB::table('product_units')->where('id', $item['product_unit_id'])->first()?->name ?? ''), code: \Illuminate\Http\Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $isPreview = (bool) $request->is_preview ?? false;
        $salesOrder = SalesOrderService::createOrder(SalesOrder::make(['raw_source' => $request->validated(), 'is_invoice' => true]), $isPreview, true);

        // if ($salesOrder && $isPreview === false) {
        //     // create history
        //     $salesOrder->details->each(function ($salesOrderDetail) use ($salesOrder) {
        //         $stockProductUnit = StockProductUnit::where('warehouse_id', $salesOrderDetail->warehouse_id)
        //             ->where('product_unit_id', $salesOrderDetail->product_unit_id)
        //             ->first(['id']);

        //         $salesOrderDetail->histories()->create([
        //             'user_id' => $salesOrder->user_id,
        //             'stock_product_unit_id' => $stockProductUnit->id,
        //             'value' => $salesOrderDetail->qty,
        //             'is_increment' => 0,
        //             'description' => 'Create SO invoice ' . $salesOrder->invoice_no,
        //             'ip' => request()->ip(),
        //             'agent' => request()->header('user-agent'),
        //         ]);
        //     });
        // }

        if (! $isPreview) {
            return $this->createdResponse();
        }

        return new SalesOrderResource($salesOrder->loadMissing('vouchers.category'));
    }

    private function validateScannedStocks(array $item): ?string
    {
        $stockProductUnit = StockProductUnit::where('product_unit_id', $item['product_unit_id'])
            ->where('warehouse_id', $item['warehouse_id'])
            ->first(['id']);

        if (! $stockProductUnit) {
            return 'Stok produk tidak sesuai';
        }

        $matchedCount = \App\Models\Stock::whereIn('id', $item['stock_ids'])
            ->where('stock_product_unit_id', $stockProductUnit->id)
            ->whereDoesntHave('salesOrderItems', fn($q) => $q->whereNotReturned())
            ->count();

        if ($matchedCount !== count($item['stock_ids'])) {
            return 'Stok produk tidak sesuai';
        }

        return null;
    }

    public function items(int $id, SalesOrderDetail $salesOrderDetail)
    {
        $salesOrder = SalesOrder::where('is_invoice', true)->findTenanted($id);
        abort_unless($salesOrderDetail->sales_order_id === $salesOrder->id, 404);

        $items = QueryBuilder::for(
            SalesOrderItem::query()
                ->where('sales_order_detail_id', $salesOrderDetail->id)
                ->select(SalesOrderItem::SELECT_COLUMNS)
                ->with('stock')
        )
            ->allowedFilters([
                'is_returned',
                'is_parent',
            ])
            ->paginate($this->per_page);

        return SalesOrderItemResource::collection($items);
    }

    public function verification(SalesOrderItemStoreRequest $request, int $id, SalesOrderDetail $salesOrderDetail)
    {
        $salesOrder = SalesOrder::where('is_invoice', true)->findTenanted($id);
        abort_unless($salesOrderDetail->sales_order_id === $salesOrder->id, 404);

        $salesOrderDetail->load(['productUnit' => fn($q) => $q->select('id', 'refer_id')]);

        $stock = Stock::where('id', $request->stock_id)
            ->whereHas(
                'stockProductUnit',
                fn($q) => $q->where('warehouse_id', $salesOrderDetail->warehouse_id)
                    ->when(
                        $salesOrderDetail->productUnit->refer_id,
                        fn($q) => $q->where('product_unit_id', $salesOrderDetail->productUnit->refer_id),
                        fn($q) => $q->where('product_unit_id', $salesOrderDetail->product_unit_id),
                    )
            )
            ->first();

        if (! $stock) {
            return response()->json(['message' => 'Stok produk tidak sesuai'], 400);
        }

        $alreadyScanned = SalesOrderItem::where('stock_id', $stock->id)->whereNotReturned()->exists();
        if ($alreadyScanned) {
            return response()->json(['message' => 'Product sudah pernah di verifikasi'], 400);
        }

        $fulfilledQty = $salesOrderDetail->salesOrderItems()->where('is_parent', 0)->count();
        if ($fulfilledQty >= $salesOrderDetail->qty) {
            return response()->json(['message' => 'Qty sudah terpenuhi'], 400);
        }

        $stock->load(['childs' => fn($q) => $q->select('id', 'parent_id')]);
        $totalChilds = $stock->childs->count();

        DB::beginTransaction();
        try {
            if ($totalChilds > 0) {
                $parentItem = $salesOrderDetail->salesOrderItems()->create([
                    'stock_id' => $stock->id,
                    'is_parent' => true,
                ]);

                $parentItem->childs()->createMany(
                    $stock->childs->map(fn($child) => [
                        'sales_order_detail_id' => $salesOrderDetail->id,
                        'stock_id' => $child->id,
                    ])->all()
                );

                $salesOrderItem = $parentItem;
            } else {
                $salesOrderItem = $salesOrderDetail->salesOrderItems()->create([
                    'stock_id' => $stock->id,
                    'is_parent' => false,
                ]);
            }

            SalesOrderService::countFulfilledQty($salesOrderDetail);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse(message: $e->getMessage(), code: (int) $e->getCode() ?: 500);
        }

        return new SalesOrderItemResource($salesOrderItem);
    }

    public function update($id, InvoiceUpdateRequest $request)
    {
        $salesOrder = SalesOrder::whereInvoice()->findTenanted($id);
        if ($salesOrder->payment_status == 'paid') {
            return response()->json(['message' => 'Invoice sudah lunas tidak dapat diupdate'], 400);
        }
        if (empty($salesOrder->invoice_no)) {
            return response()->json(['message' => 'Konversi ke invoice terlebih dahulu untuk dapat mengedit.'], 400);
        }

        $salesOrder->raw_source = $request->validated();
        $isPreview = (bool) ($request->is_preview ?? false);

        $salesOrder = SalesOrderService::updateOrder($salesOrder, $isPreview);
        // if ($salesOrder && ! $isPreview === false) {
        //     // delete old history
        //     $oldSalesOrderDetails->each(fn($salesOrderDetail) => $salesOrderDetail->histories()->delete());

        //     // create history
        //     $salesOrder->details->each(function ($salesOrderDetail) use ($salesOrder) {
        //         $stockProductUnit = StockProductUnit::where('warehouse_id', $salesOrderDetail->warehouse_id)
        //             ->where('product_unit_id', $salesOrderDetail->product_unit_id)
        //             ->first(['id']);

        //         $salesOrderDetail->histories()->create([
        //             'user_id' => $salesOrder->user_id,
        //             'stock_product_unit_id' => $stockProductUnit->id,
        //             'value' => $salesOrderDetail->qty,
        //             'is_increment' => 0,
        //             'description' => 'Create SO invoice ' . $salesOrder->invoice_no,
        //             'ip' => request()->ip(),
        //             'agent' => request()->header('user-agent'),
        //         ]);
        //     });
        // }

        if (! $isPreview) {
            return $this->updatedResponse();
        }

        return (new SalesOrderResource($salesOrder->loadMissing('vouchers.category')))->response()->setStatusCode(\Illuminate\Http\Response::HTTP_ACCEPTED);
    }

    public function destroy($id)
    {
        $salesOrder = SalesOrder::where('is_invoice', true)->findTenanted($id);
        if ($salesOrder->deliveryOrder?->is_done) {
            return response()->json(['message' => "Can't update SO if DO is already done"], 400);
        }

        // return stock if salesorder is invoice
        DB::beginTransaction();
        try {

            // if (! empty($salesOrder->invoice_no)) {
            $salesOrder->details->each(function ($salesOrderDetail) use ($salesOrder) {
                $stockProductUnit = StockProductUnit::where('warehouse_id', $salesOrderDetail->warehouse_id)
                    ->where('product_unit_id', $salesOrderDetail->product_unit_id)
                    ->first(['id']);

                $salesOrderDetail->histories()->create([
                    'user_id' => $salesOrder->user_id,
                    'stock_product_unit_id' => $stockProductUnit->id,
                    'value' => $salesOrderDetail->qty,
                    'is_increment' => 1,
                    'description' => 'Return stock from delete SO invoice ' . $salesOrder->invoice_no,
                    'ip' => request()->ip(),
                    'agent' => request()->header('user-agent'),
                ]);
            });
            // }

            $salesOrder->details->each(fn($salesOrderDetail) => $salesOrderDetail->salesOrderItems()->delete());

            // $salesOrder->details->each(fn ($salesOrderDetail) => $salesOrderDetail->histories()->delete());
            $salesOrder->forceDelete();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse(message: $e->getMessage(), code: $e->getCode() ?? 500);
        }

        return $this->deletedResponse();
    }

    public function print(string $id)
    {
        try {
            $id = Crypt::decryptString($id);
        } catch (\Throwable $th) {
        }

        return SalesOrderService::print($id, 'print-invoice', fn($q) => $q->where('is_invoice', true));
    }

    public function exportXml($id)
    {
        return SalesOrderService::exportXml($id, fn($q) => $q->where('is_invoice', true));
    }

    public function getInvoiceNo(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'warehouse_id' => ['required', new \App\Rules\TenantedRule()],
        ]);

        $warehouse = Warehouse::findOrFail($request->warehouse_id, ['id', 'code']);

        return SalesOrderService::getSoNumber($warehouse);
    }

    public function bill(string $id)
    {
        return SalesOrderService::print($id, 'print-invoice', fn($q) => $q->where('is_invoice', true));
    }

    public function export()
    {
        return Excel::download(new InvoiceExport, 'invoices.xlsx');
    }

    public function getCashiers()
    {
        // use cache 1 hour to cache cashier list
        $users = cache()->remember('cashiers', 60 * 60, function () {
            return \App\Models\User::whereIn('id', config('app.cashier_ids'))->get(['id', 'name']);
        });

        return DefaultResource::collection($users);
    }
}
