<?php

namespace App\Interfaces\Repositories\Loyalty\Claim;

use App\Interfaces\Repositories\BaseRepositoryInterface;
use App\Models\Loyalty\Claim;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ClaimRepositoryInterface extends BaseRepositoryInterface
{
    public function existsForUserInvoice(string $userId, mixed $invoiceNumber): bool;

    public function pendingTotalForUser(string $userId): int;

    public function paginateForUser(string $userId, ?string $status, int $perPage): LengthAwarePaginator;

    public function findForUser(string $userId, string $id): ?Claim;

    public function reviewEagerLoad(): array;

    public function paginateForReview(?string $status, ?string $search, ?string $sort, int $perPage): LengthAwarePaginator;

    public function findForReview(string $id): ?Claim;

    public function duplicateWarnings(Claim $claim): Collection;

    public function lockForUpdate(Claim $claim): ?Claim;
}
