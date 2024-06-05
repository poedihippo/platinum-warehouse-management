<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ReceiveOrderStoreRequest;
use App\Http\Requests\Api\ReceiveOrderUpdateRequest;
use App\Http\Resources\ReceiveOrderResource;
use App\Models\ProductUnit;
use App\Models\ReceiveOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ReceiveOrderController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        // $this->middleware('permission:receive_order_access', ['only' => ['index', 'show']]);
        $this->middleware('permission:receive_order_read', ['only' => ['index', 'show']]);
        $this->middleware('permission:receive_order_create', ['only' => 'store']);
        $this->middleware('permission:receive_order_edit', ['only' => 'update']);
        $this->middleware('permission:receive_order_delete', ['only' => 'destroy']);
        $this->middleware('permission:receive_order_done', ['only' => 'done']);
    }

    public function index()
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('receive_order_access'), 403);
        $receiveOrders = QueryBuilder::for(ReceiveOrder::tenanted()->withCount('details'))
            ->allowedFilters([
                'invoice_no',
                'is_done',
                AllowedFilter::exact('user_id'),
                AllowedFilter::exact('supplier_id'),
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::scope('start_date'),
                AllowedFilter::scope('end_date'),
            ])
            ->allowedSorts(['id', 'invoice_no', 'user_id', 'supplier_id', 'warehouse_id', 'created_at'])
            ->allowedIncludes(['details', 'user'])
            ->paginate($this->per_page);

        return ReceiveOrderResource::collection($receiveOrders);
    }

    public function show(int $id)
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('receive_order_access'), 403);
        $receiveOrder = ReceiveOrder::findTenanted($id);
        return new ReceiveOrderResource($receiveOrder->load('details')->loadCount('details'));
    }

    public function store(ReceiveOrderStoreRequest $request)
    {
        $xmlString = file_get_contents($request->file);
        $xmlObject = simplexml_load_string($xmlString);
        $json = json_encode($xmlObject);
        $xmlArray = json_decode($json, true);

        $invoiceNo = $xmlArray['TRANSACTIONS']['RECIEVEITEM']['INVOICENO'];
        if (ReceiveOrder::where('invoice_no', $invoiceNo)->exists())
            return response()->json(['message' => 'Invoice number sudah digunakan'], 400);

        $productUnitBlacklist = DB::table('product_unit_blacklists')->select('product_unit_id')->get()?->pluck('product_unit_id')?->all() ?? [];
        $supplier = Supplier::where('code', $xmlArray['TRANSACTIONS']['RECIEVEITEM']['VENDORID'])->firstOrFail();
        $warehouse = Warehouse::where('code', $xmlArray['TRANSACTIONS']['RECIEVEITEM']['WAREHOUSEID'])->firstOrFail();

        DB::beginTransaction();
        try {
            $receiveOrder = ReceiveOrder::create([
                'user_id' => auth('sanctum')->id(),
                'supplier_id' => $supplier?->id ?? null,
                'warehouse_id' => $warehouse?->id ?? null,
                'receive_datetime' => date('Y-m-d H:i:s', strtotime($request->receive_datetime)),
                'invoice_no' => $invoiceNo,
                'invoice_date' => date('Y-m-d', strtotime($xmlArray['TRANSACTIONS']['RECIEVEITEM']['INVOICEDATE'])),
                'invoice_amount' => $xmlArray['TRANSACTIONS']['RECIEVEITEM']['INVOICEAMOUNT'],
                'purchase_order_no' => $xmlArray['TRANSACTIONS']['RECIEVEITEM']['PURCHASEORDERNO'],
                'warehouse_string_id' => $xmlArray['TRANSACTIONS']['RECIEVEITEM']['WAREHOUSEID'],
                'vendor_id' => $xmlArray['TRANSACTIONS']['RECIEVEITEM']['VENDORID'],
                'sequence_no' => $xmlArray['TRANSACTIONS']['RECIEVEITEM']['SEQUENCENO'],
            ]);

            $itemlines = $xmlArray['TRANSACTIONS']['RECIEVEITEM']['ITEMLINE'];
            if (isset($itemlines['ITEMNO'])) {
                $productUnit = ProductUnit::where('code', $itemlines['ITEMNO'])->first();
                if (!$productUnit)
                    return response()->json(['message' => 'Product ' . $itemlines['ITEMNO'] . ' Tidak ditemukan on system. Please add first'], 400);

                if (!in_array($productUnit->id, $productUnitBlacklist)) {
                    $receiveOrder->details()->create([
                        'product_unit_id' => $productUnit->id,
                        'qty' => $itemlines['QUANTITY'],
                        'item_unit' => $itemlines['ITEMUNIT'],
                        'bruto_unit_price' => $itemlines['BRUTOUNITPRICE'],
                    ]);
                }
            } else {
                foreach ($itemlines as $item) {
                    $productUnit = ProductUnit::where('code', $item['ITEMNO'])->first();
                    if (!$productUnit)
                        return response()->json(['message' => 'Product ' . $item['ITEMNO'] . ' Tidak ditemukan on system. Please add first'], 400);

                    if (!in_array($productUnit->id, $productUnitBlacklist)) {
                        $receiveOrder->details()->create([
                            'product_unit_id' => $productUnit->id,
                            'qty' => $item['QUANTITY'],
                            'item_unit' => $item['ITEMUNIT'],
                            'bruto_unit_price' => $item['BRUTOUNITPRICE'],
                        ]);
                    }
                }
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => $th->getMessage()], 400);
        }

        return new ReceiveOrderResource($receiveOrder);
    }

    public function update(int $id, ReceiveOrderUpdateRequest $request)
    {
        $receiveOrder = ReceiveOrder::findTenanted($id);
        dump($request->all());
        dd($request->validated());
        $receiveOrder->update($request->validated());

        return (new ReceiveOrderResource($receiveOrder))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(int $id)
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('receive_order_delete'), 403);

        $receiveOrder = ReceiveOrder::findTenanted($id);
        if (!$receiveOrder->details->every(fn ($detail) => $detail->is_verified === false)) {
            return response()->json(['message' => 'Semua receive order harus unverified']);
        }

        $receiveOrder->delete();
        return $this->deletedResponse();
    }

    public function done(int $id, \Illuminate\Http\Request $request)
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('receive_order_done'), 403);
        $receiveOrder = ReceiveOrder::findTenanted($id);
        $request->validate(['is_done' => 'required|boolean']);

        if (!$receiveOrder->details?->every(fn ($detail) => $detail->is_verified === true)) return response()->json(['message' => 'Semua receive order harus diverifikasi'], 400);
        if (!$receiveOrder->details?->every(fn ($detail) => $detail->adjust_qty > 0)) return response()->json(['message' => 'Semua qty detail receive order harus di adjust'], 400);

        $receiveOrder->update([
            'is_done' => $request->is_done ?? 1,
            'done_at' => now(),
        ]);

        $message = 'Receive order set as ' . ($receiveOrder->is_done ? 'Done' : 'Pending');
        return response()->json(['message' => $message])->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
