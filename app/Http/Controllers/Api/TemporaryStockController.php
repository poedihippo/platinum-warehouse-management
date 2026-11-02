<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\DefaultResource;
use App\Interfaces\Services\TemporaryStock\TemporaryStockServiceInterface;
use Illuminate\Http\Request;

class TemporaryStockController extends BaseController
{
    public function __construct(private TemporaryStockServiceInterface $service)
    {
        parent::__construct();
    }

    public function index()
    {
        $products = $this->service->findAllPaginate(
            $this->per_page,
            fn ($query) => $query->with(['stock' => fn ($q) => $q->select('id', 'stock_product_unit_id')->with('stockProductUnit', fn ($q) => $q->select('id', 'product_unit_id')->with('productUnit', fn ($q) => $q->select('id', 'name', 'code', 'price')))]),
            ['id'],
            [],
            ['id', 'created_at'],
        );

        return DefaultResource::collection($products);
    }

    public function store(Request $request)
    {
        $request->validate([
            'id' => ['required', 'exists:stocks,id'],
        ]);

        $this->service->scan($request->id);

        return response()->json([
            'message' => $request->id.' stock scanned successfully',
        ]);
    }
}
