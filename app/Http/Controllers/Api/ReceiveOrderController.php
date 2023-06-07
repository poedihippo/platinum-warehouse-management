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
use Spatie\QueryBuilder\QueryBuilder;

class ReceiveOrderController extends Controller
{
    public function index()
    {
        abort_if(!auth()->user()->tokenCan('receive_orders_access'), 403);
        $receiveOrders = QueryBuilder::for(ReceiveOrder::withCount('details'))
            // ->allowedFilters('name')
            // ->allowedSorts(['id', 'name', 'created_at'])
            ->allowedIncludes(['details'])
            ->paginate();

        return ReceiveOrderResource::collection($receiveOrders);
    }

    public function show(ReceiveOrder $receiveOrder)
    {
        abort_if(!auth()->user()->tokenCan('receive_order_create'), 403);
        return new ReceiveOrderResource($receiveOrder->load('details')->loadCount('details'));
    }

    public function store(ReceiveOrderStoreRequest $request)
    {
        DB::beginTransaction();
        try {
            $xmlString = file_get_contents($request->file);
            $xmlObject = simplexml_load_string($xmlString);
            $json = json_encode($xmlObject);
            $xmlArray = json_decode($json, true);

            $supplier = Supplier::where('code', $xmlArray['TRANSACTIONS']['RECIEVEITEM']['VENDORID'])->firstOrFail();
            $warehouse = Warehouse::where('code', $xmlArray['TRANSACTIONS']['RECIEVEITEM']['WAREHOUSEID'])->firstOrFail();

            $receiveOrder = ReceiveOrder::create([
                'user_id' => auth()->user()->id,
                'supplier_id' => $supplier?->id ?? null,
                'warehouse_id' => $warehouse?->id ?? null,
                'name' => $request->name,
                'description' => $request->description,
                'receive_datetime' => date('Y-m-d H:i:s', strtotime($request->receive_datetime)),
                'invoice_no' => $xmlArray['TRANSACTIONS']['RECIEVEITEM']['INVOICENO'],
                'invoice_date' => date('Y-m-d', strtotime($xmlArray['TRANSACTIONS']['RECIEVEITEM']['INVOICEDATE'])),
                'invoice_amount' => $xmlArray['TRANSACTIONS']['RECIEVEITEM']['INVOICEAMOUNT'],
                'purchase_order_no' => $xmlArray['TRANSACTIONS']['RECIEVEITEM']['PURCHASEORDERNO'],
                'warehouse_string_id' => $xmlArray['TRANSACTIONS']['RECIEVEITEM']['WAREHOUSEID'],
                'vendor_id' => $xmlArray['TRANSACTIONS']['RECIEVEITEM']['VENDORID'],
                'sequence_no' => $xmlArray['TRANSACTIONS']['RECIEVEITEM']['SEQUENCENO'],
            ]);

            foreach ($xmlArray['TRANSACTIONS']['RECIEVEITEM']['ITEMLINE'] as $item) {
                $productUnit = ProductUnit::where('code', $item['ITEMNO'])->first();

                $receiveOrder->details()->create([
                    'product_unit_id' => $productUnit->id,
                    'qty' => $item['QUANTITY'],
                    'item_unit' => $item['ITEMUNIT'],
                    'bruto_unit_price' => $item['BRUTOUNITPRICE'],
                ]);
            }

            DB::commit();
        } catch (\Throwable $th) {
            throw $th;
        }

        return new ReceiveOrderResource($receiveOrder);
    }

    public function update(ReceiveOrder $receiveOrder, ReceiveOrderUpdateRequest $request)
    {
        dump($request->all());
        dd($request->validated());
        $receiveOrder->update($request->validated());

        return (new ReceiveOrderResource($receiveOrder))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(ReceiveOrder $receiveOrder)
    {
        abort_if(!auth()->user()->tokenCan('receive_order_delete'), 403);
        $receiveOrder->delete();
        return $this->deletedResponse();
    }
}
