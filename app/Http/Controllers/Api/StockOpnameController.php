<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StockOpnameStoreRequest;
use App\Http\Resources\StockOpnameResource;
use App\Models\StockOpname;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class StockOpnameController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        // $this->middleware('permission:stock_opname_access', ['only' => ['index', 'show']]);
        $this->middleware('permission:stock_opname_read', ['only' => ['index', 'show']]);
        $this->middleware('permission:stock_opname_create', ['only' => 'store']);
        $this->middleware('permission:stock_opname_edit', ['only' => 'update']);
        $this->middleware('permission:stock_opname_delete', ['only' => 'destroy']);
        $this->middleware('permission:stock_opname_done', ['only' => ['done', 'setDone']]);
    }

    public function index()
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('stock_opname_access'), 403);
        $stockOpnames = QueryBuilder::for(StockOpname::tenanted())
            ->allowedFilters([
                AllowedFilter::exact('warehouse_id'),
                'description',
                'is_done'
            ])
            ->allowedSorts(['id', 'description', 'is_done', 'warehouse_id', 'created_at'])
            ->paginate($this->per_page);

        return StockOpnameResource::collection($stockOpnames);
    }

    public function show(int $id)
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('stock_opname_access'), 403);
        $stockOpname = StockOpname::findTenanted($id);
        return new StockOpnameResource($stockOpname);
    }

    public function store(StockOpnameStoreRequest $request)
    {
        $stockOpname = StockOpname::create($request->validated());

        return new StockOpnameResource($stockOpname);
    }

    public function destroy(int $id)
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('stock_opname_delete'), 403);
        $stockOpname = StockOpname::findTenanted($id);
        $stockOpname->delete();
        return $this->deletedResponse();
    }

    public function done(int $id, Request $request)
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('stock_opname_done'), 403);

        $request->validate(['is_done' => 'required|boolean']);

        $stockOpname = StockOpname::findTenanted($id);
        if (!$stockOpname->details->every(fn($detail) => $detail->is_done === true))
            return response()->json(['message' => 'Semua data stock opname harus diset selesai'], 400);
        $stockOpname->update([
            'is_done' => $request->is_done,
            'done_at' => now(),
        ]);

        $message = 'Data set as ' . ($stockOpname->is_done ? 'Done' : 'Pending');
        return response()->json(['message' => $message])->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function setDone(int $id, Request $request)
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('stock_opname_done'), 403);

        $request->validate(['is_done' => 'required|boolean']);

        $stockOpname = StockOpname::findTenanted($id);
        $stockOpname->details->each->update([
            'is_done' => $request->is_done,
            'done_at' => now(),
        ]);

        $message = 'All data set as ' . ($request->is_done ? 'Done' : 'Pending');
        return response()->json(['message' => $message])->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
