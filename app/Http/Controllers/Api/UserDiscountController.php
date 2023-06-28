<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UserDiscountStoreRequest;
use App\Http\Resources\UserDiscountResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserDiscount;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\QueryBuilder;

class UserDiscountController extends Controller
{
    public function index(User $user)
    {
        abort_if(!auth()->user()->tokenCan('user_discount_access'), 403);
        $users = QueryBuilder::for(UserDiscount::with('productBrand')->where('user_id', $user->id))
            // ->allowedFilters(['name', 'email', 'phone', 'type'])
            ->allowedSorts(['id', 'product_brand_id', 'value', 'is_percentage'])
            ->paginate();

        return UserDiscountResource::collection($users);
    }

    public function show(User $user, $id)
    {
        abort_if(!auth()->user()->tokenCan('user_discount_access'), 403);
        $userDiscount = $user->userDiscounts()->where('id', $id)->firstOrFail();
        return new UserDiscountResource($userDiscount->load('productBrand'));
    }

    public function store(User $user, UserDiscountStoreRequest $request)
    {
        if (!$user) return response()->json(['message' => 'User not found'], 400);
        if ($user->type->isNot(UserType::Reseller)) return response()->json(['message' => 'User type must be reseller'], 400);
        $userDiscount = $user->userDiscounts()->create($request->validated());

        return new UserResource($userDiscount);
    }

    public function update(User $user, $id, UserDiscountStoreRequest $request)
    {
        if (!$user) return response()->json(['message' => 'User not found'], 400);
        if ($user->type->isNot(UserType::Reseller)) return response()->json(['message' => 'User type must be reseller'], 400);
        $userDiscount = $user->userDiscounts()->where('id', $id)->firstOrFail();
        $userDiscount->update($request->validated());

        return (new UserResource($userDiscount))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    // public function destroy(User $user, $id)
    // {
    //     abort_if(!auth()->user()->tokenCan('user_discount_delete'), 403);
    //     $user->userDiscounts()->where('id', $id)->delete();
    //     return $this->deletedResponse();
    // }
}
