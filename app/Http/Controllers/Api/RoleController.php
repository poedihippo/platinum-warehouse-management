<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Role\StoreRequest;
use App\Http\Requests\Api\Role\UpdateRequest;
use App\Http\Resources\Role\RoleResource;
use App\Interfaces\Services\Role\RoleServiceInterface;
use Spatie\Permission\Models\Role;

class RoleController extends BaseController
{
    public function __construct(private RoleServiceInterface $service)
    {
        parent::__construct();
        // $this->middleware('permission:role_access', ['only' => ['index', 'show']]);
        $this->middleware('permission:role_read', ['only' => ['index', 'show']]);
        $this->middleware('permission:role_create', ['only' => 'store']);
        $this->middleware('permission:role_edit', ['only' => 'update']);
        $this->middleware('permission:role_delete', ['only' => 'destroy']);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Resources\Json\ResourceCollection
     */
    public function index()
    {
        $roles = $this->service->findAllPaginate(
            $this->per_page,
            fn ($query) => $query->with('permissions'),
            ['name'],
            [],
            ['id', 'name', 'created_at'],
        );

        return RoleResource::collection($roles);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return RoleResource
     */
    public function store(StoreRequest $request)
    {
        $role = $this->service->createWithPermissions($request->name, $request->permission_ids ?? []);

        return new RoleResource($role);
    }

    /**
     * Display the specified resource.
     *
     * @return RoleResource
     */
    public function show(Role $role)
    {
        return new RoleResource($role->load('permissions'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @return RoleResource
     */
    public function update(Role $role, UpdateRequest $request)
    {
        if ($role->id == 1) {
            return response()->json(['message' => 'Role admin tidak dapat diupdate!']);
        }

        $role = $this->service->updateWithPermissions($role, $request->name, $request->permission_ids ?? []);

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
        if ($role->id == 1) {
            return response()->json(['message' => 'Role admin tidak dapat dihapus!']);
        }
        $this->service->delete($role);

        return $this->deletedResponse();
    }
}
