<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Invoice\StoreRequest;
use App\Http\Requests\Api\Invoice\UpdateRequest;
use App\Http\Requests\Api\SalesOrder\ItemStoreRequest;
use App\Http\Resources\DefaultResource;
use App\Http\Resources\SalesOrder\SalesOrderItemResource;
use App\Http\Resources\SalesOrder\SalesOrderResource;
use App\Interfaces\Services\Invoice\InvoiceServiceInterface;
use App\Interfaces\Services\SalesOrder\SalesOrderServiceInterface;
use App\Models\SalesOrderDetail;
use App\Rules\TenantedRule;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;

class InvoiceController extends BaseController
{
    public function __construct(
        private InvoiceServiceInterface $service,
        private SalesOrderServiceInterface $salesOrderService,
    ) {
        parent::__construct();
        $this->middleware('permission:invoice_read', ['only' => ['index', 'show', 'items']]);
        $this->middleware('permission:invoice_create', ['only' => ['store', 'verification']]);
        $this->middleware('permission:invoice_delete', ['only' => 'destroy']);
        $this->middleware('permission:invoice_export_xml', ['only' => 'exportXml']);
    }

    public function index()
    {
        return SalesOrderResource::collection($this->salesOrderService->index($this->per_page, fn ($q) => $q->whereInvoice()));
    }

    public function show($id)
    {
        return new SalesOrderResource($this->service->findForShow($id));
    }

    public function store(StoreRequest $request)
    {
        $this->service->ensureStockAvailable($request->items);

        $isPreview = (bool) $request->is_preview ?? false;
        $salesOrder = $this->service->createInvoice($request->validated(), $isPreview);

        return new SalesOrderResource($salesOrder);
    }

    public function items(int $id, SalesOrderDetail $salesOrderDetail)
    {
        $salesOrder = $this->salesOrderService->findInvoice($id);
        abort_unless($salesOrderDetail->sales_order_id === $salesOrder->id, 404);

        return SalesOrderItemResource::collection($this->salesOrderService->paginateItems($salesOrderDetail, $this->per_page));
    }

    public function verification(ItemStoreRequest $request, int $id, SalesOrderDetail $salesOrderDetail)
    {
        $salesOrder = $this->salesOrderService->findInvoice($id);
        abort_unless($salesOrderDetail->sales_order_id === $salesOrder->id, 404);

        return new SalesOrderItemResource($this->salesOrderService->verifyStockItem($salesOrderDetail, $request->stock_id));
    }

    public function update(int $id, UpdateRequest $request)
    {
        $salesOrder = $this->salesOrderService->findInvoice($id);
        $this->service->ensureUpdatable($salesOrder);

        $salesOrder->raw_source = $request->validated();
        $isPreview = (bool) ($request->is_preview ?? false);
        $salesOrder = $this->salesOrderService->updateOrder($salesOrder, $isPreview);

        return (new SalesOrderResource($salesOrder->loadMissing('vouchers.category')))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy($id)
    {
        $salesOrder = $this->salesOrderService->findInvoice($id);
        $this->service->ensureDeletable($salesOrder);

        try {
            $this->service->destroyInvoice($salesOrder);
        } catch (\Exception $e) {
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

        return $this->salesOrderService->print($id, 'print-invoice', fn ($q) => $q->whereInvoice());
    }

    public function exportXml($id)
    {
        return $this->salesOrderService->exportXml($id, fn ($q) => $q->whereInvoice());
    }

    public function getInvoiceNo(Request $request)
    {
        $request->validate([
            'warehouse_id' => ['required', new TenantedRule()],
        ]);

        return $this->service->invoiceNumberFor($request->warehouse_id);
    }

    public function bill(string $id)
    {
        return $this->salesOrderService->print($id, 'print-invoice', fn ($q) => $q->whereInvoice());
    }

    public function export()
    {
        return $this->service->export();
    }

    public function getCashiers()
    {
        return DefaultResource::collection($this->service->cashiers());
    }
}
