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

    public function createWithRelations(array $data, mixed $roleIds, mixed $warehouseIds): User;

    public function updateWithRelations(User $user, array $data, mixed $roleIds, mixed $warehouseIds): User;

    public function forceDeleteById(int|string $id): void;

    public function restoreById(int|string $id): User;

    public function loyaltyAdminProfile(Authenticatable $user): array;
}
