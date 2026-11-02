<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\SalesOrder\ItemStoreRequest;
use App\Http\Requests\Api\SalesOrder\MergeRequest;
use App\Http\Requests\Api\SalesOrder\StoreRequest;
use App\Http\Requests\Api\SalesOrder\UpdateRequest;
use App\Http\Resources\SalesOrder\SalesOrderItemResource;
use App\Http\Resources\SalesOrder\SalesOrderResource;
use App\Interfaces\Services\SalesOrder\SalesOrderServiceInterface;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class SalesOrderController extends BaseController
{
    public function __construct(private SalesOrderServiceInterface $service)
    {
        parent::__construct();
        // $this->middleware('permission:sales_order_access', ['only' => ['index', 'show']]);
        // $this->middleware('permission:sales_order_read', ['only' => ['index', 'show']]);
        // $this->middleware('permission:sales_order_create', ['only' => 'store']);
        // $this->middleware('permission:sales_order_edit', ['only' => 'update']);
        // $this->middleware('permission:sales_order_delete', ['only' => 'destroy']);
        // $this->middleware('permission:sales_order_print', ['only' => 'print']);
        // $this->middleware('permission:sales_order_export_xml', ['only' => 'exportXml']);
    }

    public function index()
    {
        return SalesOrderResource::collection($this->service->index($this->per_page));
    }

    public function show($id)
    {
        return new SalesOrderResource($this->service->show($id));
    }

    public function items(int $id, SalesOrderDetail $salesOrderDetail)
    {
        $salesOrder = $this->service->findScanableMarketplaceOrder($id);
        abort_unless($salesOrderDetail->sales_order_id === $salesOrder->id, 404);

        return SalesOrderItemResource::collection($this->service->paginateItems($salesOrderDetail, $this->per_page));
    }

    public function verification(ItemStoreRequest $request, int $id, SalesOrderDetail $salesOrderDetail)
    {
        $salesOrder = $this->service->findScanableMarketplaceOrder($id);
        abort_unless($salesOrderDetail->sales_order_id === $salesOrder->id, 404);

        return new SalesOrderItemResource($this->service->verifyStockItem($salesOrderDetail, $request->stock_id));
    }

    public function store(StoreRequest $request)
    {
        $salesOrder = DB::transaction(
            fn () => $this->service->createOrder(
                SalesOrder::make(['raw_source' => $request->validated()]),
                (bool) $request->is_preview
            ),
            3
        );

        return new SalesOrderResource($this->loadMarketplaceDeliveryOrder($salesOrder));
    }

    public function merge(MergeRequest $request)
    {
        $salesOrder = $this->service->mergeMarketplaceOrders($request->validated());
        $salesOrder->load('mergedSalesOrders:id,merged_into_sales_order_id,invoice_no');

        return new SalesOrderResource($this->loadMarketplaceDeliveryOrder($salesOrder));
    }

    public function update($id, UpdateRequest $request)
    {
        $salesOrder = $this->service->findTenanted($id);
        $this->service->ensureEditable($salesOrder);

        $salesOrder->raw_source = $request->validated();
        $salesOrder = $this->service->updateOrder($salesOrder, (bool) $request->is_preview ?? false);

        return (new SalesOrderResource($this->loadMarketplaceDeliveryOrder($salesOrder)))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy($id)
    {
        $this->service->destroySalesOrder($this->service->findTenanted($id));

        return $this->deletedResponse();
    }

    public function print($id, Request $request)
    {
        $mode = strtolower($request->query('mode', 'download'));
        $allowedModes = ['download', 'preview', 'inline', 'view'];

        if (! in_array($mode, $allowedModes, true)) {
            $mode = 'download';
        }

        return $this->service->print($id, 'print', null, $mode);
    }

    public function exportXml($id)
    {
        return $this->service->exportXml($id);
    }

    public function productUnitsNew()
    {
        return response()->json($this->service->paginateProductUnitsNew(request()->input('filter.warehouse_id'), $this->per_page));
    }

    public function productUnits()
    {
        return response()->json($this->service->paginateProductUnits($this->per_page));
    }

    /**
     * The DO of a marketplace or merged order is loaded (hidden) so the resource
     * can expose its reference.
     */
    private function loadMarketplaceDeliveryOrder(SalesOrder $salesOrder): SalesOrder
    {
        if ($salesOrder->marketplace || $salesOrder->isMergeResult()) {
            $salesOrder->load('deliveryOrders:id,sales_order_id')->makeHidden('deliveryOrders');
        }

        return $salesOrder;
    }
}
