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
    public function __construct()
    {
        $this->middleware('permission:uom_access', ['only' => ['index', 'show']]);
        $this->middleware('permission:uom_create', ['only' => 'store']);
        $this->middleware('permission:uom_edit', ['only' => 'update']);
        $this->middleware('permission:uom_delete', ['only' => 'destroy']);
    }

    public function index()
    {
        // abort_if(!auth()->user()->tokenCan('uom_access'), 403);
        $uoms = QueryBuilder::for(Uom::class)
            ->allowedFilters('name')
            ->allowedSorts(['id', 'name', 'created_at'])
            ->paginate();

        return UomResource::collection($uoms);
    }

    public function show(Uom $uom)
    {
        // abort_if(!auth()->user()->tokenCan('uom_access'), 403);
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
        // abort_if(!auth()->user()->tokenCan('uom_delete'), 403);
        $uom->delete();
        return $this->deletedResponse();
    }
}
