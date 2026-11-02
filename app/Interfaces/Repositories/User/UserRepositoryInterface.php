<?php

namespace App\Interfaces\Repositories\User;

use App\Interfaces\Repositories\BaseRepositoryInterface;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface extends BaseRepositoryInterface
{
    public function paginateIndex(int $perPage): LengthAwarePaginator;

    public function findByEmail(mixed $email): ?User;

    public function findWithTrashedOrFail(mixed $id): User;
}
