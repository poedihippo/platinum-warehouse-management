<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\InvoiceStoreRequest;
use App\Http\Requests\Api\InvoiceUpdateRequest;
use App\Http\Resources\SalesOrderResource;
use App\Models\SalesOrder;
use App\Models\StockProductUnit;
use App\Models\Warehouse;
use App\Services\SalesOrderService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->middleware('permission:invoice_read', ['only' => ['index', 'show']]);
        $this->middleware('permission:invoice_create', ['only' => 'store']);
        // $this->middleware('permission:invoice_edit', ['only' => 'update']);
        $this->middleware('permission:invoice_delete', ['only' => 'destroy']);
        // $this->middleware('permission:invoice_print', ['only' => 'print']);
        $this->middleware('permission:invoice_export_xml', ['only' => 'exportXml']);
    }

    public function index()
    {
        return SalesOrderService::index($this->per_page, fn ($q) => $q->where('is_invoice', true));
    }

    public function show(int $id)
    {
        $salesOrder = SalesOrderService::show($id, fn ($q) => $q->where('is_invoice', true));
        $salesOrder->id_hash = Crypt::encryptString($salesOrder->id);
        $salesOrder->whatsapp_url = empty($salesOrder->invoice_no) ? '' : SalesOrderService::getWhatsappUrl($salesOrder, $salesOrder->id_hash);

        return new SalesOrderResource($salesOrder);
    }

    public function store(InvoiceStoreRequest $request)
    {
        foreach ($request->items ?? [] as $item) {
            $stocks = \App\Models\Stock::whereAvailableStock()
                ->whereHas('stockProductUnit', fn ($q) => $q->where('product_unit_id', $item['product_unit_id'])->where('warehouse_id', $item['warehouse_id']))
                ->limit($item['qty'])
                ->get(['id']);

            if ($stocks->count() < $item['qty']) return $this->errorResponse(message: sprintf('Stok %s tidak tersedia', \Illuminate\Support\Facades\DB::table('product_units')->where('id', $item['product_unit_id'])->first()?->name ?? ''), code: \Illuminate\Http\Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $isPreview = (bool) $request->is_preview ?? false;
        $salesOrder = SalesOrderService::createOrder(SalesOrder::make(['raw_source' => $request->validated(), 'is_invoice' => true]), $isPreview, true);

        if ($salesOrder && $isPreview === false) {
            // create history
            $salesOrder->details->each(function ($salesOrderDetail) use ($salesOrder) {
                $stockProductUnit = StockProductUnit::where('warehouse_id', $salesOrderDetail->warehouse_id)
                    ->where('product_unit_id', $salesOrderDetail->product_unit_id)
                    ->first(['id']);

                $salesOrderDetail->histories()->create([
                    'user_id' => $salesOrder->user_id,
                    'stock_product_unit_id' => $stockProductUnit->id,
                    'value' => $salesOrderDetail->qty,
                    'is_increment' => 0,
                    'description' => "Create SO invoice " . $salesOrder->invoice_no,
                    'ip' => request()->ip(),
                    'agent' => request()->header('user-agent'),
                ]);
            });
        }

        return new SalesOrderResource($salesOrder);
    }

    public function update(int $id, InvoiceUpdateRequest $request)
    {
        dump($request->validated());
        $salesOrder = SalesOrder::whereInvoice()->findTenanted($id);
        dd($salesOrder);
        // kalo udah settle gabisa diupdate
        // if (!$salesOrder->details?->every(fn ($salesOrderDetail) => !$salesOrderDetail->deliveryOrderDetail))
        //     return response()->json(['message' => "DO harus dihapus terlebih dahulu sebelum mengedit SO"], 400);

        $salesOrder->raw_source = $request->validated();
        $salesOrder = SalesOrderService::updateOrder($salesOrder, (bool) $request->is_preview ?? false);
        return (new SalesOrderResource($salesOrder))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(int $id)
    {
        $salesOrder = SalesOrder::where('is_invoice', true)->findTenanted($id);
        if ($salesOrder->deliveryOrder?->is_done) return response()->json(['message' => "Can't update SO if DO is already done"], 400);

        // return stock if salesorder is invoice
        DB::beginTransaction();
        try {
            $salesOrder->details->each(function ($salesOrderDetail) use ($salesOrder) {
                $stockProductUnit = StockProductUnit::where('warehouse_id', $salesOrderDetail->warehouse_id)
                    ->where('product_unit_id', $salesOrderDetail->product_unit_id)
                    ->first(['id']);

                $salesOrderDetail->histories()->create([
                    'user_id' => $salesOrder->user_id,
                    'stock_product_unit_id' => $stockProductUnit->id,
                    'value' => $salesOrderDetail->qty,
                    'is_increment' => 1,
                    'description' => "Return stock from delete SO invoice " . $salesOrder->invoice_no,
                    'ip' => request()->ip(),
                    'agent' => request()->header('user-agent'),
                ]);
            });
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
        return SalesOrderService::print($id, 'print-invoice', fn ($q) => $q->where('is_invoice', true));
    }

    public function exportXml(int $id)
    {
        return SalesOrderService::exportXml($id, fn ($q) => $q->where('is_invoice', true));
    }

    public function getInvoiceNo(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'warehouse_id' => ['required', new \App\Rules\TenantedRule()],
        ]);

        $warehouseCode = Warehouse::findOrFail($request->warehouse_id, ['code'])?->code;

        $lastInoviceNo = SalesOrder::where('is_invoice', true)
            ->whereDate('created_at', date('Y-m-d'))
            ->where('warehouse_id', $request->warehouse_id)
            ->where('invoice_no', 'like', '%NUSATIC%')
            ->orderByDesc('invoice_no')
            ->first(['invoice_no']);

        if ($lastInoviceNo) {
            try {
                $lastInoviceNo = explode('/', $lastInoviceNo->invoice_no)[3];
                $lastInoviceNo = sprintf(config('app.format_invoice_no'), date('Y'), date('m'), date('d'), sprintf('%04s', (int) $lastInoviceNo + 1), $warehouseCode);
            } catch (\Exception $e) {
                $lastInoviceNo = SalesOrderService::getDefaultInvoiceNo($warehouseCode);
            }
        } else {
            $lastInoviceNo = SalesOrderService::getDefaultInvoiceNo($warehouseCode);
        }

        return $lastInoviceNo;
    }
}
