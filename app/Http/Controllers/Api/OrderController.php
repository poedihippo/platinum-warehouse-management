<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Order\ConvertSORequest;
use App\Http\Requests\Api\Order\OrderStoreRequest;
use App\Http\Resources\DefaultResource;
use App\Http\Resources\SalesOrderResource;
use App\Models\SalesOrder;
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
use Illuminate\Pipeline\Pipeline;
use Spatie\QueryBuilder\AllowedFilter;

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

    public function show(SalesOrder $order)
    {
        $order->load([
            'voucher.category', 'payments', 'warehouse',
            'details' => fn ($q) => $q->with(['warehouse', 'packaging']),
            'user' => fn ($q) => $q->select('id', 'name', 'type'),
            'reseller' => fn ($q) => $q->select('id', 'name', 'type', 'type', 'email', 'phone', 'address'),
            'spg' => fn ($q) => $q->select('id', 'name', 'type', 'type', 'email', 'phone', 'address'),
        ])->loadCount('details');

        return new SalesOrderResource($order);
    }

    public function store(OrderStoreRequest $request)
    {
        $rawSource = $request->validated();
        $rawSource['invoice_no'] = '';

        $pipes = [
            FillOrderAttributes::class,
            FillOrderRecords::class,
            MakeOrderDetails::class,
            CalculateAutoDiscount::class,
            CalculateVoucher::class,
            CalculateAdditionalDiscount::class,
            CalculateAdditionalFees::class,
            CheckExpectedOrderPrice::class,
        ];

        if (!$request->is_preview ?? true) $pipes[] = SaveOrder::class;

        $salesOrder = app(Pipeline::class)
            ->send(SalesOrder::make(['raw_source' => $rawSource, 'is_invoice' => true]))
            ->through($pipes)
            ->thenReturn();

        return new DefaultResource($salesOrder);
    }

    public function convertSalesOrder(SalesOrder $order, ConvertSORequest $request)
    {
        if (!empty($order->invoice_no)) {
            return response()->json(['message' => "Invoice sudah diconvert menjadi Sales Order"], 400);
        }

        $order->raw_source = $request->validated();

        $pipes = [
            FillOrderAttributes::class,
            FillOrderRecords::class,
            MakeOrderDetails::class,
            CalculateAutoDiscount::class,
            CalculateVoucher::class,
            CalculateAdditionalDiscount::class,
            CalculateAdditionalFees::class,
            CheckExpectedOrderPrice::class,
        ];

        if (!$request->is_preview ?? true) $pipes[] = ConvertToSO::class;

        $salesOrder = app(Pipeline::class)
            ->send($order)
            ->through($pipes)
            ->thenReturn();

        return new DefaultResource($salesOrder);
    }

    public function destroy(SalesOrder $order)
    {
        // abort_if(!auth()->user()->tokenCan('order_delete'), 403);
        if ($salesOrder->deliverySalesOrder?->is_done) return response()->json(['message' => "Can't update SO if DO is already done"], 400);

        $salesOrder->delete();
        return $this->deletedResponse();
    }

    // public function print(SalesOrder $order)
    // {
    //     // abort_if(!auth()->user()->tokenCan('order_print'), 403);
    //     return SalesOrderService::print($id);
    // }

    // public function exportXml(SalesOrder $order)
    // {
    //     return SalesOrderService::exportXml($id);
    // }
}
