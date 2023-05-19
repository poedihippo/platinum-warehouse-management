<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StockStoreRequest;
use App\Http\Requests\Api\StockUpdateRequest;
use App\Http\Resources\ProductUnitStockResource;
use App\Http\Resources\StockResource;
use App\Models\ProductUnit;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Spatie\QueryBuilder\QueryBuilder;

class StockController extends Controller
{
    public function index()
    {
        $query = Stock::selectRaw('stocks.product_unit_id, stocks.warehouse_id, COUNT(stocks.warehouse_id) as qty')
            ->with([
                'productUnit' => function ($q) {
                    $q->select('id', 'name', 'code', 'product_id', 'uom_id')
                        ->with('uom', fn ($q) => $q->select('id', 'name'))
                        ->with('product', function ($q) {
                            $q->select('id', 'name', 'product_category_id', 'product_brand_id')
                                ->with([
                                    'productCategory' => fn ($q) => $q->select('id', 'name'),
                                    'productBrand' => fn ($q) => $q->select('id', 'name'),
                                ]);
                        });
                },
                'warehouse' => fn ($q) => $q->select('id', 'name', 'code')
            ])
            ->groupBy('stocks.product_unit_id', 'stocks.warehouse_id');

        $stocks = QueryBuilder::for($query)
            // ->allowedFilters(['code', 'name', 'warehouse_id', 'receive_order_detail_id', 'uom_id'])
            // ->allowedSorts(['product_unit_id', 'warehouse_id', 'created_at'])
            // ->allowedIncludes(['productUnit', 'warehouse', 'receiveOrderDetail'])
            ->paginate();
        // return response()->json($stocks);

        return ProductUnitStockResource::collection($stocks);
    }

    public function details()
    {
        // abort_if(!user()->tokenCan('receive_orders_access'), 403);
        $stocks = QueryBuilder::for(Stock::class)
            ->allowedFilters(['id', 'product_unit_id', 'warehouse_id', 'receive_order_detail_id'])
            ->allowedSorts(['product_unit_id', 'warehouse_id', 'created_at'])
            ->allowedIncludes(['productUnit', 'warehouse', 'receiveOrderDetail'])
            ->paginate();

        return StockResource::collection($stocks);
    }

    public function show(Stock $stock)
    {
        // abort_if(!user()->tokenCan('receive_order_create'), 403);
        return new StockResource($stock->load('details'));
    }

    public function store(StockStoreRequest $request)
    {
        DB::beginTransaction();
        try {
            $xmlString = file_get_contents($request->file);
            $xmlObject = simplexml_load_string($xmlString);
            $json = json_encode($xmlObject);
            $xmlArray = json_decode($json, true);

            $supplier = Supplier::where('code', $xmlArray['TRANSACTIONS']['RECIEVEITEM']['VENDORID'])->first();
            $warehouse = Warehouse::where('code', $xmlArray['TRANSACTIONS']['RECIEVEITEM']['WAREHOUSEID'])->first();

            $stock = Stock::create([
                'user_id' => user()->id,
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

                $stock->details()->create([
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

        return new StockResource($stock);
    }

    public function update(Stock $stock, StockUpdateRequest $request)
    {
        dump($request->all());
        dd($request->validated());
        $stock->update($request->validated());

        return (new StockResource($stock))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(Stock $stock)
    {
        // abort_if(!user()->tokenCan('receive_order_delete'), 403);
        $stock->delete();
        return $this->deletedResponse();
    }

    public function grouping(ProductUnit $productUnit, Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'qty' => 'required|integer',
            'description' => 'required|string',
        ]);

        dump($request->all());
        dump($productUnit);

        $qtyGrouping = $request->qty ?? 0;
        $totalStock = $productUnit->stocks()->whereNull('parent_id')->count() ?? 0;
        dump($totalStock);

        if ($totalStock == 0) {
            return response()->json(['message' => 'Not enough stock'], 400);
        }

        if ($qtyGrouping == 0 || ($qtyGrouping >= $totalStock)) {
            return response()->json(['message' => 'Total amount of grouping stock exceeds the total stock'], 400);
        }

        $groupStock = Stock::create([
            'product_unit_id' => $productUnit->id,
            'warehouse_id' => $request->warehouse_id,
        ]);

        $qr = QrCode::size(300)
            ->format('svg')
            ->generate($groupStock->id);

        $groupStock->update(['qr_code' => $qr]);
        dd($groupStock);
        // 1. hitung total stock dari product unit tsb yang parent_id nya null (stock tanpa gruping)
        // 2. qty grouping harus lebih kecil dari total stock
        // 3.
    }
}
