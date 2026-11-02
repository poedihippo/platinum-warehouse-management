<?php

namespace App\Http\Services\UserDiscount;

use App\Enums\UserType;
use App\Exceptions\ApiResponseException;
use App\Http\Services\BaseService;
use App\Interfaces\Repositories\UserDiscount\UserDiscountRepositoryInterface;
use App\Interfaces\Services\UserDiscount\UserDiscountServiceInterface;
use App\Models\User;
use App\Models\UserDiscount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserDiscountService extends BaseService implements UserDiscountServiceInterface
{
    public function __construct(protected UserDiscountRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function paginateForUser(User $user, int $perPage): LengthAwarePaginator
    {
        return $this->repository->findAllPaginate(
            $perPage,
            fn ($query) => $query->with('productBrand')->where('user_id', $user->id),
            [],
            [],
            ['id', 'product_brand_id', 'value', 'is_percentage'],
        );
    }

    public function findForUser(User $user, int|string $id): UserDiscount
    {
        return $user->userDiscounts()->where('id', $id)->firstOrFail();
    }

    public function createForUser(?User $user, array $data): UserDiscount
    {
        $this->ensureReseller($user);

        return $user->userDiscounts()->create($data);
    }

    public function updateForUser(?User $user, int|string $id, array $data): UserDiscount
    {
        $this->ensureReseller($user);

        $userDiscount = $this->findForUser($user, $id);
        $this->repository->update($userDiscount, $data);

        return $userDiscount;
    }

    private function ensureReseller(?User $user): void
    {
        if (! $user) {
            throw new ApiResponseException('User Tidak ditemukan', 400);
        }
        if ($user->type->isNot(UserType::Reseller)) {
            throw new ApiResponseException('Tipe user harus reseller', 400);
        }
    }
}
