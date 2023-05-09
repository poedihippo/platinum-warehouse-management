<?php

namespace App\Http\Controllers\Api;

use App\Exports\PermissionsExport;
use App\Http\Controllers\Controller;
use App\Http\Resources\PermissionResource;
use App\Http\Requests\Api\PermissionStoreRequest;
use App\Jobs\NotifyUserOfCompletedExport;
use App\Models\Export;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\QueryBuilder;

use Yajra\DataTables\Facades\DataTables;

class PermissionController extends Controller
{
    // public function __construct()
    // {
    //     $this->middleware('permission:permissions_access', ['only' => 'index']);
    //     $this->middleware('permission:permissions_create', ['only' => ['create', 'store']]);
    //     $this->middleware('permission:permissions_edit', ['only' => ['edit', 'update']]);
    //     $this->middleware('permission:permissions_delete', ['only' => ['destroy', 'massDestroy']]);
    // }

    public function index()
    {
        $roles = QueryBuilder::for(Permission::class)
            ->allowedFilters(['name'])
            ->allowedSorts(['id', 'name', 'created_at'])
            ->orderBy('id', 'DESC')
            ->simplePaginate();

        return PermissionResource::collection($roles);
    }

    public function show(Permission $permission)
    {
        return new PermissionResource($permission);
    }

    public function store(PermissionStoreRequest $request)
    {
        $role = Permission::create($request->validated());
        $role->syncPermissions($request->permissions ?? []);
        return new PermissionResource($role);
    }

    public function update(Permission $permission, PermissionStoreRequest $request)
    {
        $permission->update($request->validated());
        return (new PermissionResource($permission))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(Permission $permission)
    {
        $permission->delete();
        return $this->deletedResponse();
    }
}
