<?php

namespace App\Interfaces\Services\UserDiscount;

use App\Interfaces\Services\BaseServiceInterface;
use App\Models\User;
use App\Models\UserDiscount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserDiscountServiceInterface extends BaseServiceInterface
{
    public function paginateForUser(User $user, int $perPage): LengthAwarePaginator;

    public function findForUser(User $user, int|string $id): UserDiscount;

    public function createForUser(?User $user, array $data): UserDiscount;

    public function updateForUser(?User $user, int|string $id, array $data): UserDiscount;
}
