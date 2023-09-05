<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RoleStoreRequest;
use App\Http\Requests\Api\RoleUpdateRequest;
use App\Http\Resources\RoleResource;
use Spatie\Permission\Models\Role;
use Spatie\QueryBuilder\QueryBuilder;

class RoleController extends Controller
{
    public function __construct()
    {
        // $this->middleware('permission:role_access', ['only' => ['index', 'show']]);
        $this->middleware('permission:role_read', ['only' => ['index', 'show']]);
        $this->middleware('permission:role_create', ['only' => 'store']);
        $this->middleware('permission:role_edit', ['only' => 'update']);
        $this->middleware('permission:role_delete', ['only' => 'destroy']);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $roles = QueryBuilder::for(Role::class)
            ->with('permissions')
            ->allowedFilters(['name'])
            ->allowedSorts(['id', 'name', 'created_at'])
            ->paginate();

        return RoleResource::collection($roles);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(RoleStoreRequest $request)
    {
        $role = new Role();
        $role->name = $request->name;
        $role->guard_name = 'web';
        $role->save();
        $role->syncPermissions($request->permission_ids ?? []);
        cache()->flush();

        return new RoleResource($role);
    }
    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Role $role)
    {
        return new RoleResource($role->load('permissions'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Role $role, RoleUpdateRequest $request)
    {
        if ($role->id == 1) return response()->json(['message' => 'Role admin can not updated!']);
        $role->name = $request->input('name');
        $role->save();
        $role->syncPermissions($request->permission_ids ?? []);
        cache()->flush();

        return new RoleResource($role);
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Role $role)
    {
        if ($role->id == 1) return response()->json(['message' => 'Role admin can not deleted!']);
        $role->delete();
        return $this->deletedResponse();
    }
}
