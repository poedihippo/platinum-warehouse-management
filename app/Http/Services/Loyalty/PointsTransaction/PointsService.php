<?php

namespace App\Http\Services\Loyalty\PointsTransaction;

use App\Http\Services\BaseService;
use App\Interfaces\Repositories\Loyalty\Claim\ClaimRepositoryInterface;
use App\Interfaces\Repositories\Loyalty\PointsTransaction\PointsTransactionRepositoryInterface;
use App\Interfaces\Services\Loyalty\PointsTransaction\PointsServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PointsService extends BaseService implements PointsServiceInterface
{
    public function __construct(
        protected PointsTransactionRepositoryInterface $repository,
        protected ClaimRepositoryInterface $claimRepository,
    ) {
        parent::__construct($repository);
    }

    /**
     * pending: points sitting in claims that are still under review.
     * approved: spendable balance, earned minus spent from the ledger.
     */
    public function balance(string $userId): array
    {
        return [
            'pending' => $this->claimRepository->pendingTotalForUser($userId),
            'approved' => $this->repository->balanceFor($userId),
        ];
    }

    public function transactions(string $userId): LengthAwarePaginator
    {
        return $this->repository->paginateForUser($userId, 20);
    }

    public function balanceFor(string $userId): int
    {
        return $this->repository->balanceFor($userId);
    }
}
