<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\UserDiscount\StoreRequest;
use App\Http\Resources\User\UserResource;
use App\Http\Resources\UserDiscount\UserDiscountResource;
use App\Interfaces\Services\UserDiscount\UserDiscountServiceInterface;
use App\Models\User;
use Illuminate\Http\Response;

class UserDiscountController extends BaseController
{
    public function __construct(private UserDiscountServiceInterface $service)
    {
        parent::__construct();
        // $this->middleware('permission:user_discount_access', ['only' => ['index', 'show']]);
        // $this->middleware('permission:user_discount_read', ['only' => ['index', 'show']]);
        $this->middleware('permission:user_discount_create', ['only' => 'store']);
        $this->middleware('permission:user_discount_edit', ['only' => 'update']);
    }

    public function index(User $user)
    {
        $users = $this->service->paginateForUser($user, $this->per_page);

        return UserDiscountResource::collection($users);
    }

    public function show(User $user, $id)
    {
        $userDiscount = $this->service->findForUser($user, $id);

        return new UserDiscountResource($userDiscount->load('productBrand'));
    }

    public function store(User $user, StoreRequest $request)
    {
        $userDiscount = $this->service->createForUser($user, $request->validated());

        return new UserResource($userDiscount);
    }

    public function update(User $user, $id, StoreRequest $request)
    {
        $userDiscount = $this->service->updateForUser($user, $id, $request->validated());

        return (new UserResource($userDiscount))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
