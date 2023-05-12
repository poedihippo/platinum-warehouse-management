<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UserStoreRequest;
use App\Http\Requests\Api\UserUpdateRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\QueryBuilder;

class UserController extends Controller
{
    public function index()
    {
        $users = QueryBuilder::for(User::class)
            ->allowedFilters(['name', 'email', 'phone', 'type'])
            ->allowedSorts(['name', 'email', 'phone', 'type'])
            ->paginate();

        return UserResource::collection($users);
    }

    public function me()
    {
        return new UserResource(user());
    }

    public function show(User $user)
    {
        return new UserResource($user);
    }

    public function store(UserStoreRequest $request)
    {
        $user = User::create($request->validated());

        return new UserResource($user);
    }

    public function update(User $user, UserUpdateRequest $request)
    {
        $user->update($request->validated());

        return (new UserResource($user))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(User $user)
    {
        $user->delete();
        return $this->deletedResponse();
    }
}
