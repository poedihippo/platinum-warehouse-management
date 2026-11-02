<?php

namespace App\Http\Services\Loyalty\LoyaltyUser;

use App\Exceptions\ApiResponseException;
use App\Http\Services\BaseService;
use App\Interfaces\Repositories\Loyalty\LoyaltyUser\LoyaltyUserRepositoryInterface;
use App\Interfaces\Repositories\Loyalty\PointsTransaction\PointsTransactionRepositoryInterface;
use App\Interfaces\Services\Loyalty\LoyaltyUser\CustomerManagementServiceInterface;
use App\Models\Loyalty\LoyaltyUser;
use App\Models\Loyalty\PointsTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class CustomerManagementService extends BaseService implements CustomerManagementServiceInterface
{
    public function __construct(
        protected LoyaltyUserRepositoryInterface $repository,
        protected PointsTransactionRepositoryInterface $pointsRepository,
    ) {
        parent::__construct($repository);
    }

    public function paginate(mixed $search, mixed $isActive, mixed $sort, int $perPage): LengthAwarePaginator
    {
        return $this->repository->paginateForAdmin($search, $isActive, $sort, $perPage);
    }

    public function findWithBalance(mixed $id): ?LoyaltyUser
    {
        $model = $this->repository->findWithCounts($id);

        if ($model) {
            $model->points_balance = $this->pointsRepository->balanceFor($model->id);
        }

        return $model;
    }

    /**
     * A positive value earns, a negative value spends. The ledger row
     * references itself as its source because a manual adjustment has no
     * claim or redemption behind it.
     */
    public function adjustPoints(LoyaltyUser $customer, mixed $points, mixed $reason, mixed $adminId): LoyaltyUser
    {
        $currentBalance = $this->pointsRepository->balanceFor($customer->id);

        if ($currentBalance + $points < 0) {
            throw new ApiResponseException('Saldo tidak cukup.', 422);
        }

        $transactionId = strtolower((string) Str::ulid());

        $this->pointsRepository->create([
            'id' => $transactionId,
            'loyalty_user_id' => $customer->id,
            'direction' => $points > 0 ? PointsTransaction::DIRECTION_EARN : PointsTransaction::DIRECTION_SPEND,
            'amount' => abs($points),
            'source_type' => PointsTransaction::SOURCE_MANUAL_ADJUSTMENT,
            'source_id' => $transactionId,
            'description' => $reason,
            'adjusted_by' => $adminId,
            'reason' => $reason,
        ]);

        $customer->points_balance = $this->pointsRepository->balanceFor($customer->id);
        $customer->loadCount(['claims', 'redemptions']);

        return $customer;
    }

    public function toggleActive(LoyaltyUser $customer): LoyaltyUser
    {
        $customer->update(['is_active' => ! $customer->is_active]);

        $customer->points_balance = $this->pointsRepository->balanceFor($customer->id);
        $customer->loadCount(['claims', 'redemptions']);

        return $customer;
    }
}
