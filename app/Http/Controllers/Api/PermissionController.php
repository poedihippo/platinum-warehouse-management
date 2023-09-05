<?php

namespace App\Http\Controllers\Api;

use App\Helpers\PermissionsHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\PermissionResource;
use App\Http\Requests\Api\PermissionStoreRequest;
use App\Http\Requests\Api\PermissionUpdateRequest;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\QueryBuilder;

class PermissionController extends Controller
{
    public function __construct()
    {
        // $this->middleware('permission:permission_access', ['only' => ['index', 'show']]);
        $this->middleware('permission:permission_read', ['only' => ['index', 'show']]);
        $this->middleware('permission:permission_create', ['only' => 'store']);
        $this->middleware('permission:permission_edit', ['only' => 'update']);
        $this->middleware('permission:permission_delete', ['only' => 'destroy']);
    }

    public function index(Request $request)
    {
        $perPage = $request->per_page ?? 100;
        // abort_if(!auth()->user()->tokenCan('permission_access'), 403);
        $roles = QueryBuilder::for(Permission::class)
            ->allowedFilters(['name'])
            ->allowedSorts(['id', 'name', 'created_at'])
            ->orderBy('id', 'DESC')
            ->paginate($perPage);

        return PermissionResource::collection($roles);
    }

    public function show(Permission $permission)
    {
        // abort_if(!auth()->user()->tokenCan('permission_access'), 403);

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
        // abort_if(!auth()->user()->tokenCan('permission_delete'), 403);
        $permission->delete();
        return $this->deletedResponse();
    }

    public function all()
    {
        return response()->json(PermissionsHelper::getAllPermissions());
        // $permissions = Permission::select('id', 'name')
        //     ->with('childs', fn ($q) => $q->select('id', 'name', 'parent_id'))
        //     ->whereParent()
        //     ->get();

        // $allPermissions = [];

        // foreach ($permissions as $permission) {
        //     $allPermissions[$permission->id] = $permission->name;
        //     // if ($permission->childs->count() > 0) {
        //     //     foreach ($permission->childs as $child) {
        //     //         // $allPermissions[$permission->id][][$child->id] = $child->name;
        //     //         dd($allPermissions);
        //     //     }
        //     // }
        // }
        // return response()->json($allPermissions);
    }
}
