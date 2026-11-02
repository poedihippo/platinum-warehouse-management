<?php

namespace App\Http\Services\StockHistory;

use App\Exports\StockHistoryExport;
use App\Http\Services\BaseService;
use App\Interfaces\Repositories\StockHistory\StockHistoryRepositoryInterface;
use App\Interfaces\Services\StockHistory\StockHistoryServiceInterface;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StockHistoryService extends BaseService implements StockHistoryServiceInterface
{
    public function __construct(protected StockHistoryRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function export(mixed $startDate, mixed $endDate): BinaryFileResponse
    {
        return Excel::download(new StockHistoryExport($startDate, $endDate), 'stock history '.$startDate.' sd '.$endDate.'.xlsx');
    }
}
