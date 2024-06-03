<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Order\OrderStoreRequest;
use App\Http\Requests\Api\Order\OrderUpdateRequest;
use App\Http\Resources\DefaultResource;
use App\Models\SalesOrder;
use App\Models\StockProductUnit;
use App\Models\UserDiscount;
use App\Services\SalesOrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class OrderController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        // $this->middleware('permission:order_access', ['only' => ['index', 'show']]);
        $this->middleware('permission:order_read', ['only' => ['index', 'show']]);
        $this->middleware('permission:order_create', ['only' => 'store']);
        $this->middleware('permission:order_edit', ['only' => 'update']);
        $this->middleware('permission:order_delete', ['only' => 'destroy']);
        $this->middleware('permission:order_print', ['only' => 'print']);
        $this->middleware('permission:order_export_xml', ['only' => 'exportXml']);
    }

    public function index()
    {
        $salesOrders = \Spatie\QueryBuilder\QueryBuilder::for(
            SalesOrder::tenanted()->withCount('details')
        )
            ->allowedFilters([
                'type',
                AllowedFilter::exact('user_id'),
                AllowedFilter::exact('spg_id'),
                AllowedFilter::exact('reseller_id'),
                AllowedFilter::scope('has_sales_order', 'hasSalesOrder'),
                AllowedFilter::scope('start_date'),
                AllowedFilter::scope('end_date'),
            ])
            ->allowedSorts(['id', 'user_id', 'spg_id', 'reseller_id', 'created_at'])
            ->allowedIncludes(['details', 'user', 'spg', 'reseller'])
            ->paginate($this->per_page);

        return DefaultResource::collection($salesOrders);
    }

    public function show(SalesOrder $salesOrder)
    {
        return $salesOrder->load([
            'voucher.category', 'payments', 'warehouse',
            'details' => fn ($q) => $q->with(['warehouse', 'packaging']),
            'user' => fn ($q) => $q->select('id', 'name', 'type'),
            'reseller' => fn ($q) => $q->select('id', 'name', 'type', 'type', 'email', 'phone', 'address'),
            'spg' => fn ($q) => $q->select('id', 'name', 'type', 'type', 'email', 'phone', 'address'),
        ])->loadCount('details');
    }

    public function store(OrderStoreRequest $request)
    {
        $rawSource = $request->validated();
        $rawSource['invoice_no'] = '';

        $salesOrder = SalesOrderService::createOrder(SalesOrder::make(['raw_source' => $rawSource]), (bool) $request->is_preview ?? false);
        return new DefaultResource($salesOrder);
    }

    public function update(SalesOrder $salesOrder, OrderUpdateRequest $request)
    {
        if (!$salesOrder->details?->every(fn ($salesOrderDetail) => !$salesOrderDetail->deliverySalesOrderDetail))
            return response()->json(['message' => "DO harus dihapus terlebih dahulu sebelum mengedit SO"], 400);

        $salesOrder->raw_source = $request->validated();
        $salesOrder = SalesOrderService::updateSalesOrder($salesOrder, (bool) $request->is_preview ?? false);
        return (new DefaultResource($salesOrder))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(SalesOrder $salesOrder)
    {
        // abort_if(!auth()->user()->tokenCan('order_delete'), 403);
        if ($salesOrder->deliverySalesOrder?->is_done) return response()->json(['message' => "Can't update SO if DO is already done"], 400);

        $salesOrder->delete();
        return $this->deletedResponse();
    }

    // public function print(SalesOrder $salesOrder)
    // {
    //     // abort_if(!auth()->user()->tokenCan('order_print'), 403);
    //     return SalesOrderService::print($id);
    // }

    // public function exportXml(SalesOrder $salesOrder)
    // {
    //     return SalesOrderService::exportXml($id);
    // }
}
