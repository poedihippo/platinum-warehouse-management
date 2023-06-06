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
    public function index()
    {
        abort_if(!auth()->user()->tokenCan('users_access'), 403);
        $users = QueryBuilder::for(User::class)
            ->allowedFilters(['name', 'email', 'phone', 'type'])
            ->allowedSorts(['name', 'email', 'phone', 'type'])
            ->paginate();

        return UserResource::collection($users);
    }

    public function me()
    {
        return new UserResource(auth()->user());
    }

    public function show(User $user)
    {
        abort_if(!auth()->user()->tokenCan('user_view'), 403);
        return new UserResource($user);
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
                'phone' => $request->phone,
                'contact_person' => $request->contact_person,
                'web_page' => $request->web_page,
                'type' => $request->type,
            ]);
            $user->syncRoles([$request->role_id]);
            return $user;
        });

        return new UserResource($user);
    }

    public function update(User $user, UserUpdateRequest $request)
    {
        $user->name = $request->name;
        $user->email = $request->email;
        $user->password = $request->password;
        $user->phone = $request->phone;
        $user->address = $request->address;
        $user->name = $request->tax_address;
        $user->tax_address = $request->provider_id;
        $user->provider_name = $request->provider_name;
        $user->city = $request->city;
        $user->province = $request->province;
        $user->zip_code = $request->zip_code;
        $user->country = $request->country;
        $user->phone = $request->phone;
        $user->contact_person = $request->contact_person;
        $user->web_page = $request->web_page;
        $user->type = $request->type;
        $user->save();

        $user->syncRoles([$request->role_id]);
        return (new UserResource($user))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(User $user)
    {
        abort_if(!auth()->user()->tokenCan('user_delete'), 403);
        $user->delete();
        return $this->deletedResponse();
    }
}
