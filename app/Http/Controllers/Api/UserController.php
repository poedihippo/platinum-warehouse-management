<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UserStoreRequest;
use App\Http\Requests\Api\UserUpdateRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder;

class UserController extends Controller
{
    public function __construct()
    {
        // $this->middleware('permission:user_access', ['only' => ['index', 'show', 'restore']]);
        $this->middleware('permission:user_access', ['only' => ['restore']]);
        $this->middleware('permission:user_read', ['only' => ['index', 'show']]);
        $this->middleware('permission:user_create', ['only' => 'store']);
        $this->middleware('permission:user_edit', ['only' => 'update']);
        $this->middleware('permission:user_delete', ['only' => ['destroy', 'forceDelete']]);
    }

    public function index()
    {
        // abort_if(!auth()->user()->tokenCan('user_access'), 403);
        $users = QueryBuilder::for(User::with(['roles' => fn ($q) => $q->select('id', 'name')]))
            ->allowedFilters(['name', 'email', 'phone', 'type'])
            ->allowedSorts(['name', 'email', 'phone', 'type'])
            ->paginate($this->per_page);

        return UserResource::collection($users);
    }

    public function me()
    {
        return new UserResource(auth()->user()?->load(['roles' => fn ($q) => $q->select('id', 'name')]));
    }

    public function show(User $user)
    {
        // abort_if(!auth()->user()->tokenCan('user_access'), 403);
        return new UserResource($user->load(['roles' => fn ($q) => $q->select('id', 'name')]));
    }

    public function store(UserStoreRequest $request)
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password ?? null,
                'phone' => $request->phone,
                'address' => $request->address,
                'tax_address' => $request->tax_address,
                'provider_id' => $request->provider_id,
                'provider_name' => $request->provider_name,
                'city' => $request->city,
                'province' => $request->province,
                'zip_code' => $request->zip_code,
                'country' => $request->country,
                'contact_person' => $request->contact_person,
                'web_page' => $request->web_page,
                'type' => $request->type,
            ]);
            $user->syncRoles($request->role_ids);
            return $user;
        });

        return new UserResource($user);
    }

    public function update(User $user, UserUpdateRequest $request)
    {
        $data = $request->validated();

        if ($request->password) {
            $data['password'] = $request->password;
        }

        $user->update($data);

        $user->syncRoles($request->role_ids);
        return (new UserResource($user))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(User $user)
    {
        if ($user->id == 1) return response()->json(['message' => 'Admin dengan id 1 tidak dapat dihapus!']);
        // abort_if(!auth()->user()->tokenCan('user_delete'), 403);
        $user->delete();
        return $this->deletedResponse();
    }

    public function forceDelete($id)
    {
        if ($id == 1) return response()->json(['message' => 'Admin dengan id 1 tidak dapat dihapus!']);
        // abort_if(!auth()->user()->tokenCan('user_delete'), 403);
        $user = User::withTrashed()->findOrFail($id);
        $user->forceDelete();
        return $this->deletedResponse();
    }

    public function restore($id)
    {
        // abort_if(!auth()->user()->tokenCan('user_access'), 403);
        $user = User::withTrashed()->findOrFail($id);
        $user->restore();
        return new UserResource($user);
    }
}
