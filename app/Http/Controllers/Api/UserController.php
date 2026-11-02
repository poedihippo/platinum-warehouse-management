<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\User\StoreRequest;
use App\Http\Requests\Api\User\UpdateRequest;
use App\Http\Resources\User\UserResource;
use App\Interfaces\Services\User\UserServiceInterface;
use App\Models\User;
use Illuminate\Http\Response;

class UserController extends BaseController
{
    public function __construct(private UserServiceInterface $service)
    {
        parent::__construct();
        // $this->middleware('permission:user_access', ['only' => ['index', 'show', 'restore']]);
        $this->middleware('permission:user_read', ['only' => ['index', 'show']]);
        $this->middleware('permission:user_create', ['only' => 'store']);
        $this->middleware('permission:user_edit', ['only' => 'update']);
        $this->middleware('permission:user_delete', ['only' => ['destroy', 'forceDelete']]);
    }

    public function index()
    {
        $users = $this->service->paginateIndex($this->per_page);

        return UserResource::collection($users);
    }

    public function me()
    {
        return new UserResource($this->service->me());
    }

    public function show(User $user)
    {
        return new UserResource($user->load([
            'roles' => fn ($q) => $q->select('id', 'name'),
            'warehouses' => fn ($q) => $q->select('id', 'name'),
        ]));
    }

    public function store(StoreRequest $request)
    {
        $user = $this->service->createWithRelations($request->validated(), $request->role_ids, $request->warehouse_ids);

        return new UserResource($user);
    }

    public function update(User $user, UpdateRequest $request)
    {
        $this->service->updateWithRelations($user, $request->validated(), $request->role_ids, $request->warehouse_ids);

        return (new UserResource($user))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(User $user)
    {
        if ($user->id == 1) {
            return response()->json(['message' => 'Admin dengan id 1 tidak dapat dihapus!']);
        }
        $this->service->delete($user);

        return $this->deletedResponse();
    }

    public function forceDelete($id)
    {
        if ($id == 1) {
            return response()->json(['message' => 'Admin dengan id 1 tidak dapat dihapus!']);
        }
        $this->service->forceDeleteById($id);

        return $this->deletedResponse();
    }

    public function restore($id)
    {
        $user = $this->service->restoreById($id);

        return new UserResource($user);
    }
}
