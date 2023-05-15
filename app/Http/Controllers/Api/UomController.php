<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UomStoreRequest;
use App\Http\Resources\UomResource;
use App\Models\Uom;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\QueryBuilder;

class UomController extends Controller
{
    public function index()
    {
        $uoms = QueryBuilder::for(Uom::class)
            ->allowedFilters('name')
            ->allowedSorts(['id', 'name', 'created_at'])
            ->paginate();

        return UomResource::collection($uoms);
    }

    public function show(Uom $uom)
    {
        return new UomResource($uom);
    }

    public function store(UomStoreRequest $request)
    {
        $uom = Uom::create($request->validated());

        return new UomResource($uom);
    }

    public function update(Uom $uom, UomStoreRequest $request)
    {
        $uom->update($request->validated());

        return (new UomResource($uom))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(Uom $uom)
    {
        $uom->delete();
        return $this->deletedResponse();
    }
}
