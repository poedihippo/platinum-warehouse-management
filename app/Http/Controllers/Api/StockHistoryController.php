<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\StockHistory\ExportRequest;
use App\Http\Resources\StockHistory\StockHistoryResource;
use App\Interfaces\Services\StockHistory\StockHistoryServiceInterface;
use Spatie\QueryBuilder\AllowedFilter;

class StockHistoryController extends BaseController
{
    public function __construct(private StockHistoryServiceInterface $service)
    {
        parent::__construct();
        // $this->middleware('permission:stock_history_access', ['only' => 'index']);
        // $this->middleware('permission:stock_history_read', ['only' => 'index']);
    }

    public function index()
    {
        $stockHistories = $this->service->findAllPaginate(
            $this->per_page,
            fn ($query) => $query->tenanted()->with([
                'stockHistoryable',
                'user' => fn ($q) => $q->select('id', 'name'),
            ]),
            [
                AllowedFilter::exact('stock_product_unit_id'),
                AllowedFilter::exact('user_id'),
                'description',
                AllowedFilter::callback('start_date', function ($query, $value) {
                    $query->where('created_at', '>=', $value);
                }),
                AllowedFilter::callback('end_date', function ($query, $value) {
                    $query->where('created_at', '<=', $value);
                }),
            ],
            [],
            ['id', 'user_id', 'description', 'created_at'],
        );

        return StockHistoryResource::collection($stockHistories);
    }

    public function export(ExportRequest $request)
    {
        return $this->service->export($request->start_date, $request->end_date);
    }
}
