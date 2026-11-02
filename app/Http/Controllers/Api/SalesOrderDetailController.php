<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\SalesOrder\SalesOrderDetailResource;
use App\Interfaces\Services\SalesOrder\SalesOrderServiceInterface;

class SalesOrderDetailController extends BaseController
{
    public function __construct(private SalesOrderServiceInterface $service)
    {
        parent::__construct();
        $this->middleware('permission:sales_order_access', ['only' => ['index', 'show']]);
    }

    public function index(int $salesOrderId)
    {
        return SalesOrderDetailResource::collection($this->service->details($salesOrderId));
    }

    public function show(int $salesOrderId, $salesOrderDetailId)
    {
        return new SalesOrderDetailResource($this->service->findDetail($salesOrderId, $salesOrderDetailId));
    }
}
