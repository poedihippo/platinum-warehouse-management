<?php

namespace App\Http\Controllers\Api;

use App\Exports\StockExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Stock\AddToStockRequest;
use App\Http\Requests\Api\Stock\GroupingByScanRequest;
use App\Http\Requests\Api\Stock\GroupingRequest;
use App\Http\Requests\Api\Stock\SetToPrintingQueueRequest;
use App\Http\Requests\Api\StockRecordRequest;
use App\Http\Requests\Api\StockRepackRequest;
use App\Http\Requests\Api\Stock\VerifyRequest;
use App\Http\Resources\StockProductUnitResource;
use App\Http\Resources\Stocks\BaseStockResource;
use App\Http\Resources\Stocks\StockProductUnitResource as StocksStockProductUnitResource;
use App\Imports\StockImport;
use App\Models\AdjustmentRequest;
use App\Models\ReceiveOrderDetail;
use App\Models\Stock;
use App\Models\StockProductUnit;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class StockController extends Controller
{
    const FORMAT_GROUPING = "%s GR-%s%s";

    public function __construct()
    {
        parent::__construct();
        // $this->middleware('permission:stock_access', ['only' => ['index', 'show', 'details']]);
        $this->middleware('permission:stock_read', ['only' => ['index', 'show', 'details']]);
        $this->middleware('permission:stock_create', ['only' => 'store']);
        $this->middleware('permission:stock_edit', ['only' => 'update']);
        $this->middleware('permission:stock_delete', ['only' => 'destroy']);
        $this->middleware('permission:stock_grouping', ['only' => ['grouping', 'ungrouping']]);
        $this->middleware('permission:stock_print', ['only' => 'printAll']);
    }

    public function index()
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('stock_access'), 403);
        $stockProductUnits = QueryBuilder::for(StockProductUnit::select('stock_product_units.id', 'stock_product_units.qty', 'stock_product_units.product_unit_id', 'stock_product_units.warehouse_id')
            ->tenanted()
            ->has('productUnit')
            ->has('warehouse')
            ->with([
                'warehouse' => fn($q) => $q->select('warehouses.id', 'warehouses.name'),
                'productUnit'
            ])
            ->withCount(['stocks' => fn($q) => $q->whereAvailableStock()->whereIsStock()->whereNull('description')]))
            ->allowedFilters([
                AllowedFilter::exact('id'),
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::exact('product_unit_id'),
                AllowedFilter::scope('product_unit'),
                AllowedFilter::scope('company', 'whereCompany'),
                // AllowedFilter::scope('product_brand_id', 'whereProductBrandId'),
                // AllowedFilter::scope('product_category_id', 'whereProductCategoryId'),
            ])
            ->allowedSorts([
                'id',
                'created_at',
                // 'qty',
                // 'product_unit_id',
                // 'warehouse_id'
            ])
            ->paginate($this->per_page);

        return StockProductUnitResource::collection($stockProductUnits);
    }

    public function details()
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('stock_access'), 403);

        $filter = request()->filter;

        if (isset($filter) && !empty($filter['parent_id'])) {
            $query = Stock::tenanted()->whereNotNull('parent_id');
        } else {
            // $query = Stock::tenanted()->whereNull('parent_id');
            $query = Stock::tenanted();
        }

        if (isset($filter) && !empty($filter['is_show_group']) && $filter['is_show_group'] == 1) {
            $query->withCount('childs');
        } else {
            $query->isShowGroup(0);
        }

        $stocks = QueryBuilder::for($query)
            ->allowedFilters([
                AllowedFilter::exact('id'),
                AllowedFilter::exact('printer_id'),
                AllowedFilter::exact('parent_id'),
                AllowedFilter::exact('stock_product_unit_id'),
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::exact('receive_order_id'),
                AllowedFilter::exact('receive_order_detail_id'),
                AllowedFilter::exact('is_tempel'),
                AllowedFilter::scope('is_show_group'),
                AllowedFilter::scope('start_date'),
                AllowedFilter::scope('end_date'),
                AllowedFilter::callback('show_all', function (\Illuminate\Database\Eloquent\Builder $query, $value) {
                    if (!$value == 0) $query->whereAvailableStock();
                }),
                'in_printing_queue',
            ])
            ->allowedSorts(['in_printing_queue', 'printed_at', 'scanned_count', 'scanned_datetime', 'warehouse_id', 'created_at'])
            // ->allowedIncludes(['productUnit', 'warehouse', 'receiveOrderDetail'])
            ->paginate($this->per_page);

        return BaseStockResource::collection($stocks);
    }

    public function show(int $id)
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('stock_access'), 403);
        $stock = Stock::findTenanted($id);
        return new StocksStockProductUnitResource($stock->load([
            'stockProductUnit' => fn($q) => $q->tenanted()->withCount(['stocks' => fn($q) => $q->whereAvailableStock()->whereNull('description')]),
            'receiveOrderDetail'
        ]));
    }

    public function store(Request $request)
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('stock_create'), 403);
        return response()->json($request->all());
    }

    public function destroy(Stock $stock)
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('stock_delete'), 403);

        if ($stock->childs?->count() > 0) return response()->json(['message' => 'Tidak dapat menghapus stock parent'], 400);
        if ($stock->salesOrderItems?->count() > 0) return response()->json(['message' => 'Tidak dapat menghapus stock. Stock sudah masuk di Sales Order'], 400);

        $stock->delete();
        return $this->deletedResponse();
    }

    public function groupingByScan(GroupingByScanRequest $request)
    {
        $stockProductUnit = StockProductUnit::findTenanted($request->stock_product_unit_id);
        $totalStock = $stockProductUnit->stocks()->whereNull('parent_id')->doesntHave('childs')->count() ?? 0;

        if ($totalStock == 0 || $totalStock < count($request->ids)) {
            return response()->json(['message' => 'Stock tidak mencukupi'], 400);
        }

        $productUnit = $stockProductUnit->productUnit;

        //cari nama grouping berdasarkan self::FORMAT_GROUPING
        $description = $request->name;
        if (!$description) {
            $lastGroupName = $stockProductUnit->stocks()->whereNull('parent_id')->has('childs')->whereMonth('stocks.created_at', date('m'))->whereYear('stocks.created_at', date('Y'))->orderByDesc('description')->first(['description'])?->description ?? "";
            $lastOrderNumberGroup = explode(" ", $lastGroupName);
            $lastOrderNumberGroup = $lastOrderNumberGroup[1] ?? "";
            if ($lastOrderNumberGroup) {
                $lastOrderNumberGroup = (int) substr($lastOrderNumberGroup, -3);
            } else {
                $lastOrderNumberGroup = 000;
            }

            $formatMMYY = date('my');
            $description = sprintf(self::FORMAT_GROUPING, $productUnit->code, $formatMMYY, sprintf('%03d', (int) $lastOrderNumberGroup + 1));
        }

        DB::transaction(function () use ($request, $stockProductUnit, $description) {
            $expiredDate = $request->expired_date ? date('Y-m-d', strtotime($request->expired_date)) : null;
            $groupStock = Stock::create([
                'stock_product_unit_id' => $stockProductUnit->id,
                'description' => $description,
                'expired_date' => $expiredDate,
            ]);

            $data = ['parent_id' => $groupStock->id];
            if ($request->expired_date) {
                $data['expired_date'] = $expiredDate;
            }

            Stock::whereIn('id', $request->ids)->update($data);
        });

        return response()->json(['message' => 'Group stock berhasil dibuat'], 201);
    }

    public function grouping(GroupingRequest $request)
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('stock_grouping'), 403);
        $totalQtyGrouping = $request->total_group * $request->qty;
        $receiveOrderDetailId = $request->receive_order_detail_id ?? null;

        // grouping dari page stock
        if ($request->stock_product_unit_id) {
            $stockProductUnit = StockProductUnit::findTenanted($request->stock_product_unit_id);
            $totalStock = $stockProductUnit->stocks()->whereNull('parent_id')->doesntHave('childs')->count() ?? 0;

            if ($totalStock == 0) {
                return response()->json(['message' => 'Stock tidak mencukupi'], 400);
            }

            if ($totalQtyGrouping == 0 || ($totalQtyGrouping > $totalStock)) {
                return response()->json(['message' => 'Jumlah total grouping stok melebihi total stok'], 400);
            }

            // $totalGroupStock = $stockProductUnit->stocks()->whereNull('parent_id')->has('childs')->count() ?? 0;
            $productUnit = $stockProductUnit->productUnit;

            //cari nama grouping berdasarkan self::FORMAT_GROUPING
            $lastGroupName = $stockProductUnit->stocks()->whereNull('parent_id')->has('childs')->whereMonth('stocks.created_at', date('m'))->whereYear('stocks.created_at', date('Y'))->orderByDesc('description')->first(['description'])?->description ?? "";
            $lastOrderNumberGroup = explode(" ", $lastGroupName);
            $lastOrderNumberGroup = $lastOrderNumberGroup[1] ?? "";
            if ($lastOrderNumberGroup) {
                $lastOrderNumberGroup = (int) substr($lastOrderNumberGroup, -3);
            } else {
                $lastOrderNumberGroup = 000;
            }
        } elseif ($request->receive_order_detail_id) {
            //grouping dari page RO
            $receiveOrderDetail = ReceiveOrderDetail::findOrFail($request->receive_order_detail_id);
            $totalStock = $receiveOrderDetail->stocks()->whereNull('parent_id')->doesntHave('childs')->count() ?? 0;

            if ($totalStock == 0) {
                return response()->json(['message' => 'Stock tidak mencukupi'], 400);
            }

            if ($totalQtyGrouping == 0 || ($totalQtyGrouping > $totalStock)) {
                return response()->json(['message' => 'Jumlah total grouping stok melebihi total stok'], 400);
            }

            // $totalGroupStock = $receiveOrderDetail->stocks()->whereNull('parent_id')->has('childs')->count() ?? 0;

            $productUnit = $receiveOrderDetail->productUnit;
            $stockProductUnit = StockProductUnit::tenanted()->where('warehouse_id', $receiveOrderDetail->receiveOrder->warehouse_id)
                ->where('product_unit_id', $receiveOrderDetail->product_unit_id)
                ->first();
        }

        // 1. hitung total stock dari product unit tsb yang parent_id nya null (stock tanpa gruping)
        // 2. qty grouping harus lebih kecil dari total stock
        // 3.

        $formatMMYY = date('my');
        for ($i = 0; $i < $request->total_group; $i++) {
            $description = sprintf(self::FORMAT_GROUPING, $productUnit->code, $formatMMYY, sprintf('%03d', (int) $lastOrderNumberGroup + 1));
            // $totalGroupStock++;
            $lastOrderNumberGroup++;
            $groupStock = Stock::create([
                'stock_product_unit_id' => $stockProductUnit->id,
                // 'product_unit_id' => $productUnit->id,
                // 'warehouse_id' => $warehouseId,
                'receive_order_detail_id' => $receiveOrderDetailId,
                // 'description' => 'Group ' . $totalGroupStock . ' - ' . $productUnit->code,
                'description' => $description,
            ]);

            // $data = QrCode::size(350)
            //     ->format('png')
            //     // ->merge(public_path('images/logo-platinum.png'), absolute: true)
            //     ->generate($groupStock->id);

            // $folder = 'qrcode/';
            // $fileName = 'group/' . $groupStock->id . '.png';
            // $fullPath = $folder . $fileName;
            // Storage::put($fullPath, $data);

            // $groupStock->update(['qr_code' => $fullPath]);

            // $stocks = Stock::tenanted()->where('product_unit_id', $productUnit->id)->where('warehouse_id', $warehouseId)->whereNull('parent_id')->where('id', '<>', $groupStock->id)->doesntHave('childs');

            if ($request->stock_product_unit_id) {
                $stocks = $stockProductUnit->stocks()->whereNull('parent_id')->where('id', '<>', $groupStock->id)->doesntHave('childs');
            } else {
                $stocks = $receiveOrderDetail->stocks()->whereNull('parent_id')->where('id', '<>', $groupStock->id)->doesntHave('childs');
            }

            if (is_null($groupStock->receive_order_detail_id)) {
                $stocks->orderBy('receive_order_detail_id');
            } else {
                $stocks->where('receive_order_detail_id', $groupStock->receive_order_detail_id);
            }

            $data = [
                'parent_id' => $groupStock->id,
                'is_stock' => 1,
            ];

            if ($request->expired_date) {
                $data['expired_date'] = $request->expired_date;
            }

            $stocks->limit($request->qty)->get()?->each->update($data);
        }

        return response()->json(['message' => 'Group stock berhasil dibuat'], 201);
    }

    public function ungrouping(int $id)
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('stock_grouping'), 403);
        $stock = Stock::findTenanted($id);
        if ($stock->childs->isEmpty()) return response()->json(['message' => 'Stock bukan grouping / tidak memiliki childs'], 400);

        DB::beginTransaction();
        try {
            $stock->delete();
            Stock::where('parent_id', $stock->id)->update(['parent_id' => null]);
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 400);
        }

        return response()->json(['message' => 'success']);
    }

    public function printAll(Request $request)
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('stock_print'), 403);

        $filter = $request->filter;
        $query = Stock::tenanted()->select('id', 'parent_id', 'qr_code', 'description');

        if (isset($filter) && isset($filter['stock_product_unit_id']) && $filter['stock_product_unit_id'] != '') {
            $query->where('stock_product_unit_id', $filter['stock_product_unit_id']);
        }

        if (isset($filter) && isset($filter['receive_order_detail_id']) && $filter['receive_order_detail_id'] != '') {
            $query->where('receive_order_detail_id', $filter['receive_order_detail_id']);
        }

        if (isset($filter) && isset($filter['parent_id']) && $filter['parent_id'] != '') {
            if ($request->print_with_parent == 1) {
                $query->where('id', $filter['parent_id'])->orWhere('parent_id', $filter['parent_id'])->orderBy('parent_id');
            } else {
                $query->where('parent_id', $filter['parent_id']);
            }
        } else {
            $query->whereNull('parent_id');
        }

        $stocks = QueryBuilder::for($query)
            ->allowedFilters(['id', 'receive_order_detail_id', 'stock_product_unit_id', 'parent_id', 'product_unit_id', 'warehouse_id'])
            ->allowedSorts(['product_unit_id', 'warehouse_id', 'created_at'])
            ->get();

        return response()->json($stocks);
    }

    public function record(StockRecordRequest $request)
    {
        $stockIds = $request->stock_ids;
        $stockIds = is_array($stockIds) && count($stockIds) > 0 ? $stockIds : [];
        $columnName = '';
        $value = '';
        if ($stockProductUnitId = $request->stock_product_unit_id) {
            $columnName = 'stock_product_unit_id';
            $value = $stockProductUnitId;
        } elseif ($receiveOrderDetailId = $request->receive_order_detail_id) {
            $columnName = 'receive_order_detail_id';
            $value = $receiveOrderDetailId;
        }

        if ($columnName != '' && $value != '') {
            DB::beginTransaction();
            try {
                $time = now();
                if (count($stockIds) > 0) {
                    foreach ($stockIds as $id) {
                        $stock = Stock::tenanted()->where($columnName, $value)->where('id', $id)->first();
                        if ($stock) {
                            if ($stock->childs->count() > 0) {
                                // as parent & has childs
                                $stock->increment('scanned_count', 1, ['scanned_datetime' => $time]);
                                $stock->childs->each->increment('scanned_count', 1, ['scanned_datetime' => $time]);
                            } else {
                                $stock->increment('scanned_count', 1, ['scanned_datetime' => $time]);
                            }
                        }
                    }
                } elseif ($request->is_print_all) {
                    DB::table('stocks')->where($columnName, $value)->increment('scanned_count', 1, ['scanned_datetime' => $time]);
                }
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                return response()->json(['message' => $e->getMessage()], 400);
            }

            return response()->json(['message' => 'Data berhasil diupdate']);
        }

        return response()->json(['message' => 'Tidak ada data yang diupdate']);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xls,xlsx,csv'
        ]);

        // Excel::queueImport(new StockImport($request->warehouse_id ?? 1), $request->file);
        Excel::import(new StockImport($request->warehouse_id ?? 1), $request->file);
        die('duarrr nmax');
    }

    public function verificationTempel(int $id, Request $request)
    {
        $request->validate(["is_tempel" => "required"]);
        $stock = Stock::findTenanted($id, ['id', 'is_tempel']);
        $isTempel = filter_var($request->is_tempel, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
        $stock->update(["is_tempel" => $isTempel]);

        return new BaseStockResource($stock);
    }

    public function repack(int $id, StockRepackRequest $request)
    {
        $stock = Stock::findTenanted($id, ['id', 'parent_id', 'stock_product_unit_id']);
        if ($stock->childs?->count() > 0) return response()->json(['message' => 'Tidak dapat me-repack stock parent'], 400);
        if ($stock->salesOrderItems?->count() > 0) return response()->json(['message' => 'Tidak dapat me-repack stock. Stock sudah masuk di Sales Order'], 400);

        $qty = $request->qty;
        $stockProductUnit = StockProductUnit::findOrFail($request->stock_product_unit_id);
        $userId = auth('sanctum')->user()->id;
        $userIp = request()->ip();
        $userAgent = request()->header('user-agent');
        $createdAt = $request->created_at ? date('Y-m-d H:i:s', strtotime($request->created_at)) : now();

        DB::beginTransaction();
        try {
            // hapus stock
            $stock->delete();
            $stock->stockProductUnit->histories()->create([
                'user_id' => $userId,
                'stock_product_unit_id' => $stock->stockProductUnit->id,
                'value' => 1,
                'is_increment' => 0,
                'description' => sprintf("Delete stock untuk repack - %s ke %s(sebanyak %d)", $stock->stockProductUnit->productUnit->name ?? "", $stockProductUnit->productUnit->name ?? "", $qty),
                'ip' => $userIp,
                'agent' => $userAgent,
                'created_at' => $createdAt,
            ]);

            // adjust stock berdasarkan stock_product_unit_id dan qty nya
            // create AdjustmentRequest
            $adjustmentRequest = AdjustmentRequest::create([
                'user_id' => $userId,
                'approved_by' => $userId,
                'stock_product_unit_id' => $stockProductUnit->id,
                'value' => $qty,
                'is_increment' => 1,
                'is_approved' => 1,
                'description' => sprintf("Add stock from repack - %s(sebanyak %d)", $stock->stockProductUnit->productUnit?->name ?? "", $qty),
            ]);

            if ($stockProductUnit->productUnit->is_generate_qr) {
                \App\Jobs\GenerateStockQrcode::dispatchSync($stockProductUnit, $qty);
            } else {
                $stockProductUnit->increment('qty', $qty);
            }

            // create history
            $adjustmentRequest->histories()->create([
                'user_id' => $userId,
                'stock_product_unit_id' => $stockProductUnit->id,
                'value' => $qty,
                'is_increment' => 1,
                'description' => $adjustmentRequest->description,
                'ip' => $userIp,
                'agent' => $userAgent,
                'created_at' => $createdAt,
            ]);
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(["message" => $e->getMessage()], 500);
        }

        return response()->json(["message" => sprintf("Berhasil me-repack %s ke %s sebanyak %d", $stock->stockProductUnit->productUnit?->name ?? "", $stockProductUnit->productUnit?->name ?? "", $qty)]);
    }

    public function export()
    {
        return Excel::download(new StockExport, 'stock-' . date('Y-m-d H:i') . '.xlsx');
    }

    public function setToPrinted(VerifyRequest $request)
    {
        Stock::whereIn('id', $request->stocks)->update([
            'printed_at' => now(),
            'in_printing_queue' => 0,
        ]);

        return response()->json([
            'message' => count($request->stocks) . ' stocks set to printed successfully',
        ]);
    }

    public function setToPrintingQueue(SetToPrintingQueueRequest $request)
    {
        $data = [
            'printer_id' => $request->printer_id,
            'printed_at' => null,
            'in_printing_queue' => 1,
        ];

        if ($request->expired_date) {
            $data['expired_date'] = $request->expired_date;
        }

        Stock::whereIn('id', $request->stocks)->update($data);

        return response()->json([
            'message' => count($request->stocks) . ' stocks added to printing queue',
        ]);
    }

    public function printVerification(VerifyRequest $request)
    {
        Stock::whereIn('id', $request->stocks)->update([
            'printed_at' => now(),
            'in_printing_queue' => 0,
        ]);

        return response()->json([
            'message' => count($request->stocks) . ' stocks scanned successfully',
        ]);
    }

    public function addToStock(AddToStockRequest $request)
    {
        Stock::whereIn('id', $request->ids)->update([
            'is_stock' => $request->is_add,
        ]);

        return response()->json([
            'message' => count($request->ids) . ' stocks ' . ($request->is_add ? 'added' : 'removed') . ' successfully',
        ]);
    }
}
