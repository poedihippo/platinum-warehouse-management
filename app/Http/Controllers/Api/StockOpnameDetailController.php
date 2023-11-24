<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StockOpnameDetailStoreRequest;
use App\Http\Requests\Api\StockOpnameDetailUpdateRequest;
use App\Http\Resources\StockOpnameDetailResource;
use App\Models\StockOpname;
use App\Models\StockOpnameDetail;
use App\Models\StockOpnameItem;
use App\Models\StockProductUnit;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\QueryBuilder;

class StockOpnameDetailController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:stock_opname_access', ['only' => ['index', 'show']]);
        $this->middleware('permission:stock_opname_create', ['only' => 'store']);
        $this->middleware('permission:stock_opname_edit', ['only' => 'update']);
        $this->middleware('permission:stock_opname_done', ['only' => 'done']);
    }

    public function index(StockOpname $stockOpname)
    {
        // abort_if(!auth()->user()->tokenCan('stock_opname_access'), 403);

        $query = StockOpnameDetail::where('stock_opname_id', $stockOpname->id)
            ->with('stockProductUnit.productUnit')
            ->withCount([
                'stockOpnameItems',
                'stockOpnameItems as total_scanned_qty' => fn ($q) => $q->where('is_scanned', 1)
            ]);

        $stockOpnameDetails = QueryBuilder::for($query)
            // ->allowedFilters(['description'])
            ->allowedSorts(['id', 'created_at'])
            ->allowedIncludes('stockOpname')
            ->paginate();

        return StockOpnameDetailResource::collection($stockOpnameDetails);
    }

    public function show(StockOpname $stockOpname, $stockOpnameDetailId)
    {
        // abort_if(!auth()->user()->tokenCan('stock_opname_access'), 403);

        $stockOpnameDetail = $stockOpname->details()->where('id', $stockOpnameDetailId)
            ->with(['stockOpname', 'stockProductUnit.productUnit'])
            ->withCount([
                'stockOpnameItems',
                'stockOpnameItems as total_scanned_qty' => fn ($q) => $q->where('is_scanned', 1)
            ])
            ->firstOrFail();

        return new StockOpnameDetailResource($stockOpnameDetail);
    }

    public function store(StockOpname $stockOpname, StockOpnameDetailStoreRequest $request)
    {
        $stockProductUnit = StockProductUnit::select('id')
            ->where('warehouse_id', $stockOpname->warehouse_id)
            ->where('product_unit_id', $request->product_unit_id)
            ->firstOrFail();

        $stockOpnameDetail = $stockOpname->details()->create([
            'stock_product_unit_id' => $stockProductUnit->id,
            'qty' => $stockProductUnit->stocks->count() ?? 0,
        ]);

        return new StockOpnameDetailResource($stockOpnameDetail);
    }

    public function update(StockOpname $stockOpname, $id, StockOpnameDetailUpdateRequest $request)
    {
        $stockOpnameDetail = $stockOpname->details()->where('id', $id)->update($request->validated());

        return (new StockOpnameDetailResource($stockOpnameDetail))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function scan(StockOpname $stockOpname, $stockOpnameDetailId, Request $request)
    {
        $request->validate([
            'stock_id' => 'required|exists:stocks,id'
        ]);

        $stockOpnameDetail = $stockOpname->details()->where('id', $stockOpnameDetailId)->first();
        if (!$stockOpnameDetail) return response()->json(['message' => 'Data stock opname not match'], 400);

        $stockOpnameItem = $stockOpnameDetail->stockOpnameItems()->where('stock_id', $request->stock_id)->first();
        if (!$stockOpnameItem) return response()->json(['message' => 'QR does not match the stock opname data'], 400);

        $isScanned = $request->is_scanned ?? 1;

        $stockOpnameItem->is_scanned = $isScanned;
        if (!$stockOpnameItem->isDirty('is_scanned') && $stockOpnameItem->is_scanned == 1) return response()->json(['message' => 'Stock has been scanned'], 400);
        $stockOpnameItem->save();
        $message = 'Stock scanned successfully';

        // if stock have childs, update childs too
        $stock = $stockOpnameItem->stock;
        if($stock->childs->count() > 0){
            StockOpnameItem::whereIn('stock_id', $stock->childs->pluck('id'))->update(['is_scanned' => $isScanned]);
            $message = 'Stock and all childs scanned successfully';
        }

        return response()->json(['message' => $message], Response::HTTP_ACCEPTED);
    }

    public function done(StockOpname $stockOpname, string $id, Request $request)
    {
        // abort_if(!auth()->user()->tokenCan('stock_opname_done'), 403);

        $stockOpnameDetail = $stockOpname->details()->where('id', $id)->first();
        if (!$stockOpnameDetail) return response()->json(['message' => 'Data stock opname not match'], 400);

        $stockOpnameDetail->update([
            'is_done' => $request->is_done,
            'done_at' => now(),
        ]);
        $message = 'Data set as ' . ($stockOpnameDetail->is_done ? 'Done' : 'Pending');
        return response()->json(['message' => $message])->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
