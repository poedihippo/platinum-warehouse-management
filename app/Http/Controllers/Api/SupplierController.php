<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SupplierStoreRequest;
use App\Http\Requests\Api\SupplierUpdateRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SupplierController extends Controller
{
    public function __construct()
    {
        // $this->middleware('permission:supplier_access', ['only' => ['index', 'show']]);
        $this->middleware('permission:supplier_read', ['only' => ['index', 'show']]);
        $this->middleware('permission:supplier_create', ['only' => 'store']);
        $this->middleware('permission:supplier_edit', ['only' => 'update']);
        $this->middleware('permission:supplier_delete', ['only' => 'destroy']);
    }

    public function index()
    {
        // abort_if(!auth()->user()->tokenCan('supplier_access'), 403);
        $suppliers = QueryBuilder::for(Supplier::class)
            ->allowedFilters([
                AllowedFilter::callback('search', function (Builder $query, $value) {
                    $query->where('name', 'like', '%' . $value . '%')
                        ->orWhere('email', 'like', '%' . $value . '%')
                        ->orWhere('phone', 'like', '%' . $value . '%');
                }),
            ])
            ->allowedSorts(['id', 'name'])
            ->paginate();

        return SupplierResource::collection($suppliers);
    }

    public function show(Supplier $supplier)
    {
        // abort_if(!auth()->user()->tokenCan('supplier_access'), 403);
        return new SupplierResource($supplier);
    }

    public function store(SupplierStoreRequest $request)
    {
        $supplier = Supplier::create($request->validated());

        return new SupplierResource($supplier);
    }

    public function update(Supplier $supplier, SupplierUpdateRequest $request)
    {
        $supplier->update($request->validated());

        return (new SupplierResource($supplier))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(Supplier $supplier)
    {
        // abort_if(!auth()->user()->tokenCan('supplier_delete'), 403);
        $supplier->delete();
        return $this->deletedResponse();
    }
}
