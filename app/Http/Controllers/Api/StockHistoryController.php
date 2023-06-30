<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StockHistoryResource;
use App\Models\StockHistory;
use Spatie\QueryBuilder\QueryBuilder;

class StockHistoryController extends Controller
{
    public function index()
    {
        abort_if(!auth()->user()->tokenCan('stock_history_access'), 403);

        $stockHistories = QueryBuilder::for(StockHistory::with(['stockHistoryable', 'user' => fn ($q) => $q->select('id', 'name')]))
            ->allowedFilters([
                'description',
                'user_id'
            ])
            ->allowedSorts(['id', 'user_id', 'description', 'created_at'])
            ->paginate();

        return StockHistoryResource::collection($stockHistories);
    }

    // public function show(StockHistory $StockHistory)
    // {
    //     abort_if(!auth()->user()->tokenCan('stock_history_access'), 403);
    //     return new StockHistoryResource($StockHistory->load('stockProductUnit'));
    // }

    // public function store(StockHistoryStoreRequest $request)
    // {
    //     $StockHistory = StockHistory::create($request->validated());

    //     return new StockHistoryResource($StockHistory->load('stockProductUnit'));
    // }

    // public function update(StockHistory $StockHistory, StockHistoryStoreRequest $request)
    // {
    //     if ($StockHistory->is_approved) return response()->json(['message' => "Can't update data if it has been approved"], 400);
    //     $StockHistory->update($request->validated());

    //     return (new StockHistoryResource($StockHistory->load('stockProductUnit')))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    // }

    // public function destroy(StockHistory $StockHistory)
    // {
    //     abort_if(!auth()->user()->tokenCan('stock_history_delete'), 403);
    //     if ($StockHistory->is_approved) return response()->json(['message' => "Can't delete data if it has been approved"], 400);

    //     $StockHistory->delete();
    //     return $this->deletedResponse();
    // }
}
