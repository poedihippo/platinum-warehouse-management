<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Uom\StoreRequest;
use App\Http\Resources\Uom\UomResource;
use App\Interfaces\Services\Uom\UomServiceInterface;
use App\Models\Uom;
use Illuminate\Http\Response;

class UomController extends BaseController
{
    public function __construct(private UomServiceInterface $service)
    {
        parent::__construct();
        // $this->middleware('permission:uom_access', ['only' => ['index', 'show']]);
        // $this->middleware('permission:uom_read', ['only' => ['index', 'show']]);
        $this->middleware('permission:uom_create', ['only' => 'store']);
        $this->middleware('permission:uom_edit', ['only' => 'update']);
        $this->middleware('permission:uom_delete', ['only' => 'destroy']);
    }

    public function index()
    {
        $uoms = $this->service->findAllPaginate(
            $this->per_page,
            null,
            ['name'],
            [],
            ['id', 'name', 'created_at'],
        );

        return UomResource::collection($uoms);
    }

    public function show(Uom $uom)
    {
        return new UomResource($uom);
    }

    public function store(StoreRequest $request)
    {
        $uom = $this->service->create($request->validated());

        return new UomResource($uom);
    }

    public function update(Uom $uom, StoreRequest $request)
    {
        $this->service->update($uom, $request->validated());

        return (new UomResource($uom))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(Uom $uom)
    {
        $this->service->delete($uom);

        return $this->deletedResponse();
    }
}
