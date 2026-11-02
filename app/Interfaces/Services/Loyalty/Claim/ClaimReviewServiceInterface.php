<?php

namespace App\Interfaces\Services\Loyalty\Claim;

use App\Interfaces\Services\BaseServiceInterface;
use App\Models\Loyalty\Claim;
use App\Models\Loyalty\ClaimLineItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ClaimReviewServiceInterface extends BaseServiceInterface
{
    public function paginate(?string $status, ?string $search, ?string $sort, int $perPage): LengthAwarePaginator;

    public function findForReview(string $id): ?Claim;

    public function addLineItem(string $claimId, mixed $productUnitId, mixed $quantity): ClaimLineItem;

    public function updateLineItem(string $claimId, string $lineItemId, mixed $quantity): ClaimLineItem;

    public function removeLineItem(string $claimId, string $lineItemId): void;

    public function approve(string $claimId, mixed $adminId): Claim;

    public function reject(string $claimId, mixed $adminId, mixed $reason): Claim;
}
