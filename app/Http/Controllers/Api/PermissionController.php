<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermissionResource;
use App\Http\Requests\Api\PermissionStoreRequest;
use App\Http\Requests\PermissionUpdateRequest;
use App\Models\Permission;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\QueryBuilder;

class PermissionController extends Controller
{
    public function index()
    {
        abort_if(!auth()->user()->tokenCan('permission_access'), 403);
        $roles = QueryBuilder::for(Permission::class)
            ->allowedFilters(['name'])
            ->allowedSorts(['id', 'name', 'created_at'])
            ->orderBy('id', 'DESC')
            ->paginate();

        return PermissionResource::collection($roles);
    }

    public function show(Permission $permission)
    {
        abort_if(!auth()->user()->tokenCan('permission_access'), 403);

        return new PermissionResource($permission);
    }

    public function store(PermissionStoreRequest $request)
    {
        $permission = Permission::create($request->validated());
        return new PermissionResource($permission);
    }

    public function update(Permission $permission, PermissionUpdateRequest $request)
    {
        $permission->update($request->validated());
        return (new PermissionResource($permission))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(Permission $permission)
    {
        abort_if(!auth()->user()->tokenCan('permission_delete'), 403);
        $permission->delete();
        return $this->deletedResponse();
    }
}
