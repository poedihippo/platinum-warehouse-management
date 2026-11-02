<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Permission\StoreRequest;
use App\Http\Requests\Api\Permission\UpdateRequest;
use App\Http\Resources\Permission\PermissionResource;
use App\Interfaces\Services\Permission\PermissionServiceInterface;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PermissionController extends BaseController
{
    public function __construct(private PermissionServiceInterface $service)
    {
        parent::__construct();
        // $this->middleware('permission:permission_access', ['only' => ['index', 'show']]);
        $this->middleware('permission:permission_read', ['only' => ['index', 'show']]);
        $this->middleware('permission:permission_create', ['only' => 'store']);
        $this->middleware('permission:permission_edit', ['only' => 'update']);
        $this->middleware('permission:permission_delete', ['only' => 'destroy']);
    }

    public function index(Request $request)
    {
        $perPage = $request->per_page ?? 100;
        $roles = $this->service->paginateIndex($perPage);

        return PermissionResource::collection($roles);
    }

    public function show(Permission $permission)
    {
        return new PermissionResource($permission);
    }

    public function store(StoreRequest $request)
    {
        $permission = $this->service->create($request->validated());

        return new PermissionResource($permission);
    }

    public function update(Permission $permission, UpdateRequest $request)
    {
        $this->service->update($permission, $request->validated());

        return (new PermissionResource($permission))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(Permission $permission)
    {
        $this->service->delete($permission);

        return $this->deletedResponse();
    }

    public function all()
    {
        return response()->json($this->service->allPermissions());
    }
}
