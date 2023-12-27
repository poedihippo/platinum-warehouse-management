<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StockHistoryResource;
use App\Models\StockHistory;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class StockHistoryController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        // $this->middleware('permission:stock_history_access', ['only' => 'index']);
        $this->middleware('permission:stock_history_read', ['only' => 'index']);
    }

    public function index()
    {
        // abort_if(!auth()->user()->tokenCan('stock_history_access'), 403);

        $stockHistories = QueryBuilder::for(StockHistory::with(['stockHistoryable', 'user' => fn($q) => $q->select('id', 'name')]))
            ->allowedFilters([
                AllowedFilter::exact('stock_product_unit_id'),
                AllowedFilter::exact('user_id'),
                'description',
                'user_id'
            ])
            ->allowedSorts(['id', 'user_id', 'description', 'created_at'])
            ->paginate($this->per_page);

        return StockHistoryResource::collection($stockHistories);
    }
}
