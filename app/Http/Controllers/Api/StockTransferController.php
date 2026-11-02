<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\StockTransfer\TransferRequest;
use App\Interfaces\Services\StockTransfer\StockTransferServiceInterface;

class StockTransferController extends BaseController
{
    public function __construct(private StockTransferServiceInterface $service)
    {
        parent::__construct();
        $this->middleware('permission:stock_edit', ['only' => ['transfer']]);
    }

    public function transfer(TransferRequest $request)
    {
        $this->service->transfer($request->input(), $request->has('stock_ids'));

        return $this->createdResponse('Transfer stock berhasil');
    }
}
