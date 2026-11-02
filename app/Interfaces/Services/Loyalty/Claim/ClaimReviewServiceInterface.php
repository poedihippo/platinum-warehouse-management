<?php

namespace App\Interfaces\Services\Loyalty\Claim;

use App\Interfaces\Services\BaseServiceInterface;
use App\Models\Loyalty\Claim;
use App\Models\Loyalty\ClaimLineItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ClaimReviewServiceInterface extends BaseServiceInterface
{
    public function paginate(mixed $status, mixed $search, mixed $sort, int $perPage): LengthAwarePaginator;

    public function findForReview(mixed $id): ?Claim;

    public function addLineItem(mixed $claimId, mixed $productUnitId, mixed $quantity): ClaimLineItem;

    public function updateLineItem(mixed $claimId, mixed $lineItemId, mixed $quantity): ClaimLineItem;

    public function removeLineItem(mixed $claimId, mixed $lineItemId): void;

    public function approve(mixed $claimId, mixed $adminId): Claim;

    public function reject(mixed $claimId, mixed $adminId, mixed $reason): Claim;
}
