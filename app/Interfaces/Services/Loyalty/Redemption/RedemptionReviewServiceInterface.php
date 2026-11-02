<?php

namespace App\Interfaces\Services\Loyalty\Redemption;

use App\Interfaces\Services\BaseServiceInterface;
use App\Models\Loyalty\Redemption;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface RedemptionReviewServiceInterface extends BaseServiceInterface
{
    public function paginate(mixed $status, mixed $search, mixed $sort, int $perPage): LengthAwarePaginator;

    public function findForReview(mixed $id): ?Redemption;

    public function approve(mixed $id, mixed $adminId): Redemption;

    public function reject(mixed $id, mixed $adminId, mixed $reason): Redemption;

    public function ship(mixed $id, mixed $adminId, mixed $trackingNumber, mixed $shippingCarrier): Redemption;

    public function deliver(mixed $id, mixed $adminId): Redemption;
}
