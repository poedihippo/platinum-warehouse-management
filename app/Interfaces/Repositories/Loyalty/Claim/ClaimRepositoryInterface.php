<?php

namespace App\Interfaces\Repositories\Loyalty\Claim;

use App\Interfaces\Repositories\BaseRepositoryInterface;
use App\Models\Loyalty\Claim;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ClaimRepositoryInterface extends BaseRepositoryInterface
{
    public function existsForUserInvoice(mixed $userId, mixed $invoiceNumber): bool;

    public function pendingTotalForUser(mixed $userId): int;

    public function paginateForUser(mixed $userId, mixed $status, int $perPage): LengthAwarePaginator;

    public function findForUser(mixed $userId, mixed $id): ?Claim;

    public function reviewEagerLoad(): array;

    public function paginateForReview(mixed $status, mixed $search, mixed $sort, int $perPage): LengthAwarePaginator;

    public function findForReview(mixed $id): ?Claim;

    public function duplicateWarnings(Claim $claim): Collection;

    public function lockForUpdate(Claim $claim): ?Claim;
}
