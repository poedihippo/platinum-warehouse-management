<?php

namespace App\Http\Services\User;

use App\Http\Services\BaseService;
use App\Interfaces\Repositories\User\UserRepositoryInterface;
use App\Interfaces\Services\User\UserServiceInterface;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class UserService extends BaseService implements UserServiceInterface
{
    /**
     * Loyalty permissions the admin SPA knows about. Kept in sync with
     * LoyaltyPermissionSeeder.
     */
    private const LOYALTY_PERMISSIONS = [
        'manage prizes',
        'review redemptions',
        'manage loyalty points',
        'review claims',
        'manage brands',
        'manage customers',
    ];

    public function __construct(protected UserRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function paginateIndex(int $perPage): LengthAwarePaginator
    {
        return $this->repository->paginateIndex($perPage);
    }

    public function me(): ?User
    {
        return auth('sanctum')->user()?->load(['roles' => fn ($q) => $q->select('id', 'name')]);
    }

    public function createWithRelations(mixed $data, mixed $roleIds, mixed $warehouseIds): User
    {
        return DB::transaction(function () use ($data, $roleIds, $warehouseIds) {
            $user = $this->repository->create($data);
            $user->syncRoles($roleIds);
            $user->load('roles');
            $user->warehouses()->sync($warehouseIds);

            return $user;
        });
    }

    public function updateWithRelations(User $user, mixed $data, mixed $roleIds, mixed $warehouseIds): User
    {
        $this->repository->update($user, $data);

        $user->syncRoles($roleIds);
        $user->load('roles');
        $user->warehouses()->sync($warehouseIds);

        return $user;
    }

    public function forceDeleteById(mixed $id): void
    {
        $user = $this->repository->findWithTrashedOrFail($id);
        $user->forceDelete();
    }

    public function restoreById(mixed $id): User
    {
        $user = $this->repository->findWithTrashedOrFail($id);
        $user->restore();

        return $user;
    }

    /**
     * Checked through can() so the admin role's Gate::before bypass is reflected.
     */
    public function loyaltyAdminProfile(Authenticatable $user): array
    {
        $permissions = collect(self::LOYALTY_PERMISSIONS)
            ->filter(fn (string $permission) => $user->can($permission))
            ->values();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->roles->pluck('name'),
            'permissions' => $permissions,
        ];
    }
}
