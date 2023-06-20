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

    public function done(StockOpname $stockOpname, Request $request)
    {
        $request->validate(['is_done' => 'required|boolean']);

        if (!$stockOpname->details->every(fn ($detail) => $detail->is_done === true)) return response()->json(['message' => 'All stock opname data must be set done'], 400);
        $stockOpname->update(['is_done' => $request->is_done]);

        $message = 'Data set as ' . ($stockOpname->is_done ? 'Done' : 'Pending');
        return response()->json(['message' => $message])->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function setDone(StockOpname $stockOpname, Request $request)
    {
        $request->validate(['is_done' => 'required|boolean']);

        $stockOpname->details->each->update([
            'is_done' => $request->is_done,
            'done_at' => now(),
        ]);

        $message = 'All data set as ' . ($request->is_done ? 'Done' : 'Pending');
        return response()->json(['message' => $message])->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
