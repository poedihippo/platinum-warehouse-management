<?php

namespace App\Interfaces\Services\Loyalty\Redemption;

use App\Interfaces\Services\BaseServiceInterface;
use App\Models\Loyalty\Redemption;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface RedemptionReviewServiceInterface extends BaseServiceInterface
{
    public function paginate(?string $status, ?string $search, ?string $sort, int $perPage): LengthAwarePaginator;

    public function findForReview(string $id): ?Redemption;

    public function approve(string $id, mixed $adminId): Redemption;

    public function reject(string $id, mixed $adminId, mixed $reason): Redemption;

    public function ship(string $id, mixed $adminId, mixed $trackingNumber, mixed $shippingCarrier): Redemption;

    public function deliver(string $id, mixed $adminId): Redemption;
}
