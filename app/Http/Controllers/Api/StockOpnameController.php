<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StockOpnameStoreRequest;
use App\Http\Requests\StockOpnameUpdateRequest;
use App\Http\Resources\StockOpnameResource;
use App\Models\StockOpname;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\QueryBuilder;

class StockOpnameController extends Controller
{
    public function index()
    {
        abort_if(!auth()->user()->tokenCan('stock_opnames_access'), 403);
        $stockOpnames = QueryBuilder::for(StockOpname::query())
            ->allowedFilters(['description'])
            ->allowedSorts(['id', 'created_at'])
            ->paginate();

        return StockOpnameResource::collection($stockOpnames);
    }

    public function show(StockOpname $stockOpname)
    {
        abort_if(!auth()->user()->tokenCan('stock_opname_create'), 403);
        return new StockOpnameResource($stockOpname);
    }

    public function store(StockOpnameStoreRequest $request)
    {
        $stockOpname = StockOpname::create($request->validated());

        return new StockOpnameResource($stockOpname);
    }

    public function update(StockOpname $stockOpname, StockOpnameUpdateRequest $request)
    {
        $stockOpname->update($request->validated());

        return (new StockOpnameResource($stockOpname))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(StockOpname $stockOpname)
    {
        abort_if(!auth()->user()->tokenCan('stock_opname_delete'), 403);
        $stockOpname->delete();
        return $this->deletedResponse();
    }

    public function verification(StockOpname $stockOpname, Request $request)
    {
        $stockOpname->update(['is_verified' => $request->is_verified]);

        return (new StockOpnameResource($stockOpname))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
