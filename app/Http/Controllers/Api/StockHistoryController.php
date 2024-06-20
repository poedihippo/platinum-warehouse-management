<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StockHistory\StockHistoryExport;
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
        // abort_if(!auth('sanctum')->user()->tokenCan('stock_history_access'), 403);

        $stockHistories = QueryBuilder::for(StockHistory::tenanted()->with(['stockHistoryable', 'user' => fn ($q) => $q->select('id', 'name')]))
            ->allowedFilters([
                AllowedFilter::exact('stock_product_unit_id'),
                AllowedFilter::exact('user_id'),
                'description',
            ])
            ->allowedSorts(['id', 'user_id', 'description', 'created_at'])
            ->paginate($this->per_page);

        return StockHistoryResource::collection($stockHistories);
    }

    public function export(StockHistoryExport $request)
    {
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\StockHistoryExport($request->start_date, $request->end_date), 'stock history ' . $request->start_date . ' sd ' . $request->end_date . '.xlsx');
    }
}
