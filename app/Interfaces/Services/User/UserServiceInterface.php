<?php

namespace App\Interfaces\Services\User;

use App\Interfaces\Services\BaseServiceInterface;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserServiceInterface extends BaseServiceInterface
{
    public function paginateIndex(int $perPage): LengthAwarePaginator;

    public function me(): ?User;

    public function createWithRelations(mixed $data, mixed $roleIds, mixed $warehouseIds): User;

    public function updateWithRelations(User $user, mixed $data, mixed $roleIds, mixed $warehouseIds): User;

    public function forceDeleteById(mixed $id): void;

    public function restoreById(mixed $id): User;

    public function loyaltyAdminProfile(Authenticatable $user): array;
}
