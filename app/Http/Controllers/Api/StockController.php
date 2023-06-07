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
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Spatie\QueryBuilder\QueryBuilder;

class StockController extends Controller
{
    public function index()
    {
        $query = ProductUnit::selectRaw('product_units.id, product_units.product_id, product_units.uom_id, product_units.name, product_units.code, stocks.warehouse_id, warehouses.name as warehouse, COUNT(stocks.warehouse_id) as qty')
            ->leftJoin('stocks', 'stocks.product_unit_id', '=', 'product_units.id')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'stocks.warehouse_id')
            ->groupBy('product_units.id', 'product_units.product_id', 'product_units.uom_id', 'product_units.name', 'product_units.code', 'stocks.warehouse_id', 'warehouse');

        // return response()->json($stocks);
        $stocks = QueryBuilder::for($query)
            ->allowedFilters(['code', 'name', 'warehouse_id', 'receive_order_id', 'receive_order_detail_id', 'uom_id'])
            ->allowedSorts(['product_unit_id', 'warehouse_id', 'created_at'])
            // ->allowedIncludes(['productUnit', 'warehouse', 'receiveOrderDetail'])
            ->paginate();

        return ProductUnitStockResource::collection($stocks);
    }

    public function details()
    {
        // abort_if(!auth()->user()->tokenCan('receive_orders_access'), 403);

        $filter = request()->filter;

        if (isset($filter) && isset($filter['parent_id']) && $filter['parent_id'] != '') {
            $query = Stock::whereNotNull('parent_id');
        } else {
            $query = Stock::withCount('childs')->whereNull('parent_id');
        }

        $stocks = QueryBuilder::for($query)
            ->allowedFilters(['id', 'parent_id', 'product_unit_id', 'warehouse_id', 'receive_order_id', 'receive_order_detail_id'])
            ->allowedSorts(['product_unit_id', 'warehouse_id', 'created_at'])
            ->allowedIncludes(['productUnit', 'warehouse', 'receiveOrderDetail'])
            ->paginate();

        return StockResource::collection($stocks);
    }

    public function show(Stock $stock)
    {
        // abort_if(!auth()->user()->tokenCan('receive_order_create'), 403);
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
        // abort_if(!auth()->user()->tokenCan('receive_order_delete'), 403);
        $stock->delete();
        return $this->deletedResponse();
    }

    public function grouping(ProductUnit $productUnit, Request $request)
    {
        $request->validate([
            'total_group' => 'required|integer|gt:0',
            'qty' => 'required|integer|gt:0',
            'warehouse_id' => 'required|exists:warehouses,id',
            'receive_order_detail_id' => 'nullable|exists:receive_order_details,id',
        ]);

        $totalQtyGrouping = $request->total_group * $request->qty;
        $receiveOrderDetailId = $request->receive_order_detail_id ?? null;

        $totalStock = $productUnit->stocks()->whereNull('parent_id')->doesntHave('childs');
        if (!is_null($receiveOrderDetailId)) {
            $totalStock->where('receive_order_detail_id', $receiveOrderDetailId);
        }

        // 1. hitung total stock dari product unit tsb yang parent_id nya null (stock tanpa gruping)
        // 2. qty grouping harus lebih kecil dari total stock
        // 3.

        $totalStock = $totalStock->count() ?? 0;
        if ($totalStock == 0) {
            return response()->json(['message' => 'Not enough stock'], 400);
        }

        if ($totalQtyGrouping == 0 || ($totalQtyGrouping > $totalStock)) {
            return response()->json(['message' => 'Total amount of grouping stock exceeds the total stock'], 400);
        }

        $totalGroupStock = $productUnit->stocks()->whereNull('parent_id')->has('childs')->count() ?? 0;

        for ($i = 0; $i < $request->total_group; $i++) {
            $totalGroupStock++;
            $groupStock = Stock::create([
                'product_unit_id' => $productUnit->id,
                'warehouse_id' => $request->warehouse_id,
                'receive_order_detail_id' => $receiveOrderDetailId,
                'description' => 'Group ' . $totalGroupStock . ' - ' . $productUnit->code,
            ]);

            $data = QrCode::size(350)
                ->format('png')
                ->merge(public_path('images/logo-platinum.png'), absolute: true)
                ->generate($groupStock->id);

            $folder = 'qrcode/';
            $fileName = 'group/' . $groupStock->id . '.png';
            $fullPath = $folder .  $fileName;
            Storage::put($fullPath, $data);

            $groupStock->update(['qr_code' => $fullPath]);

            $stocks = Stock::where('product_unit_id', $productUnit->id)->whereNull('parent_id')->doesntHave('childs')->where('id', '<>', $groupStock->id)->limit($request->qty);

            if (!is_null($groupStock->receive_order_detail_id)) {
                $stocks->orderBy('receive_order_detail_id');
            }

            $stocks->get()?->each->update([
                'parent_id' => $groupStock->id
            ]);

            // $productUnit->stocks()->whereNull('parent_id')->doesntHave('childs')->where('id', '<>', $groupStock->id)->orderByDesc('created_at')->limit($request->qty)->update([
            //     'parent_id' => $groupStock->id
            // ]);
        }

        return response()->json(['message' => 'Stock group created successfully'], 201);
    }

    public function printAll(Request $request)
    {
        $filter = $request->filter;
        $query = Stock::select('id', 'parent_id', 'qr_code');

        if (isset($filter) && isset($filter['parent_id']) && $filter['parent_id'] != '') {
            $query->where('parent_id', $filter['parent_id']);
        } else {
            $query->whereNull('parent_id');
        }
        //  else {
        //     $query->whereNull('parent_id')->with('childs', fn ($q) => $q->select('id', 'parent_id', 'qr_code', 'description'));
        // }

        $stocks = QueryBuilder::for($query)
            ->allowedFilters(['id', 'receive_order_detail_id', 'parent_id', 'product_unit_id', 'warehouse_id'])
            ->allowedSorts(['product_unit_id', 'warehouse_id', 'created_at'])
            ->get();

        return response()->json($stocks);
    }
}
