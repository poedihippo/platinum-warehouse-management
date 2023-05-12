<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ReceiveOrderDetailStoreRequest;
use App\Http\Requests\Api\ReceiveOrderDetailUpdateRequest;
use App\Http\Resources\ReceiveOrderDetailResource;
use App\Models\ReceiveOrder;
use App\Models\ReceiveOrderDetail;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\QueryBuilder;

class ReceiveOrderDetailController extends Controller
{
    public function index(ReceiveOrder $receiveOrder)
    {
        $receiveOrderDetails = QueryBuilder::for(ReceiveOrderDetail::class)
            // ->allowedFilters('name')
            // ->allowedSorts(['id', 'name', 'created_at'])
            ->simplePaginate();

        return ReceiveOrderDetailResource::collection($receiveOrderDetails);
    }

    public function show(ReceiveOrder $receiveOrder, ReceiveOrderDetail $receiveOrderDetail)
    {
        return new ReceiveOrderDetailResource($receiveOrderDetail);
    }

    public function store(ReceiveOrder $receiveOrder, ReceiveOrderDetailStoreRequest $request)
    {
        die('belum dibuat cok');
        // dump($request->all());
        // DB::beginTransaction();
        // try {
        //     $xmlString = file_get_contents($request->file);
        //     $xmlObject = simplexml_load_string($xmlString);
        //     $json = json_encode($xmlObject);
        //     $xmlArray = json_decode($json, true);

        //     $supplier = Supplier::where('code', $xmlArray['TRANSACTIONS']['RECIEVEITEM']['VENDORID'])->first();
        //     $warehouse = Warehouse::where('code', $xmlArray['TRANSACTIONS']['RECIEVEITEM']['WAREHOUSEID'])->first();

        //     $receiveOrderDetail = ReceiveOrderDetail::create([
        //         'user_id' => user()->id,
        //         'supplier_id' => $supplier?->id ?? null,
        //         'warehouse_id' => $warehouse?->id ?? null,
        //         'name' => $request->name,
        //         'description' => $request->description,
        //         'receive_datetime' => date('Y-m-d H:i:s', strtotime($request->receive_datetime)),
        //         'invoice_no' => $xmlArray['TRANSACTIONS']['RECIEVEITEM']['INVOICENO'],
        //         'invoice_date' => date('Y-m-d', strtotime($xmlArray['TRANSACTIONS']['RECIEVEITEM']['INVOICEDATE'])),
        //         'invoice_amount' => $xmlArray['TRANSACTIONS']['RECIEVEITEM']['INVOICEAMOUNT'],
        //         'purchase_order_no' => $xmlArray['TRANSACTIONS']['RECIEVEITEM']['PURCHASEORDERNO'],
        //         'warehouse_string_id' => $xmlArray['TRANSACTIONS']['RECIEVEITEM']['WAREHOUSEID'],
        //         'vendor_id' => $xmlArray['TRANSACTIONS']['RECIEVEITEM']['VENDORID'],
        //         'sequence_no' => $xmlArray['TRANSACTIONS']['RECIEVEITEM']['SEQUENCENO'],
        //     ]);

        //     foreach ($xmlArray['TRANSACTIONS']['RECIEVEITEM']['ITEMLINE'] as $item) {
        //         $productUnit = ProductUnit::where('code', $item['ITEMNO'])->first();

        //         $receiveOrderDetail->details()->create([
        //             'product_unit_id' => $productUnit->id,
        //             'qty' => $item['QUANTITY'],
        //             'item_unit' => $item['ITEMUNIT'],
        //             'bruto_unit_price' => $item['BRUTOUNITPRICE'],
        //         ]);
        //     }

        //     DB::commit();
        // } catch (\Throwable $th) {
        //     throw $th;
        // }

        // return new ReceiveOrderDetailResource($receiveOrderDetail);
    }

    public function update(ReceiveOrder $receiveOrder, ReceiveOrderDetail $receiveOrderDetail, ReceiveOrderDetailUpdateRequest $request)
    {
        $receiveOrderDetail->update($request->validated());

        return (new ReceiveOrderDetailResource($receiveOrderDetail))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(ReceiveOrder $receiveOrder, ReceiveOrderDetail $receiveOrderDetail)
    {
        $receiveOrderDetail->delete();
        return $this->deletedResponse();
    }
}
