<?php

namespace App\Http\Services\Loyalty\Redemption;

use App\Exceptions\ApiResponseException;
use App\Http\Services\BaseService;
use App\Interfaces\Repositories\Loyalty\PointsTransaction\PointsTransactionRepositoryInterface;
use App\Interfaces\Repositories\Loyalty\Prize\PrizeRepositoryInterface;
use App\Interfaces\Repositories\Loyalty\Redemption\RedemptionRepositoryInterface;
use App\Interfaces\Services\Loyalty\Redemption\RedemptionServiceInterface;
use App\Models\Loyalty\LoyaltyUser;
use App\Models\Loyalty\PointsTransaction;
use App\Models\Loyalty\Redemption;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RedemptionService extends BaseService implements RedemptionServiceInterface
{
    public function __construct(
        protected RedemptionRepositoryInterface $repository,
        protected PrizeRepositoryInterface $prizeRepository,
        protected PointsTransactionRepositoryInterface $pointsRepository,
    ) {
        parent::__construct($repository);
    }

    /**
     * The pending check guards against double taps; the balance check under the
     * prize row lock is what prevents overspending.
     */
    public function redeem(LoyaltyUser $user, mixed $input, mixed $saveAddress): Redemption
    {
        $prizeId = $input['prize_id'] ?? null;

        if ($this->repository->hasPending($user->getKey(), $prizeId)) {
            throw new ApiResponseException('Anda masih memiliki penukaran hadiah ini yang sedang diproses.', 409);
        }

        $result = DB::transaction(function () use ($user, $prizeId, $input, $saveAddress) {
            $prize = $this->prizeRepository->lockForUpdate($prizeId);

            if (! $prize || ! $prize->is_active) {
                return ['error' => ['message' => 'Hadiah tidak ditemukan.', 'code' => 404]];
            }

            if ($prize->stock <= 0) {
                return ['error' => ['message' => 'Hadiah sudah habis.', 'code' => 422]];
            }

            $balance = $this->pointsRepository->balanceFor($user->getKey());
            if ($balance < $prize->points_cost) {
                return ['error' => ['message' => 'Poin tidak cukup.', 'code' => 422]];
            }

            $prize->decrement('stock');

            $redemption = $this->repository->create([
                'loyalty_user_id' => $user->getKey(),
                'prize_id' => $prize->id,
                'points_spent' => $prize->points_cost,
                'quantity' => 1,
                'status' => Redemption::STATUS_PENDING,
                'recipient_name' => $input['recipient_name'] ?? null,
                'recipient_phone' => $input['recipient_phone'] ?? null,
                'recipient_address' => $input['recipient_address'] ?? null,
                'recipient_notes' => $input['recipient_notes'] ?? null,
                'submitted_at' => now(),
            ]);

            if ($saveAddress) {
                $user->update(['address' => $input['recipient_address'] ?? null]);
            }

            $this->pointsRepository->create([
                'loyalty_user_id' => $user->getKey(),
                'direction' => PointsTransaction::DIRECTION_SPEND,
                'amount' => $prize->points_cost,
                'source_type' => PointsTransaction::SOURCE_REDEMPTION,
                'source_id' => $redemption->id,
                'description' => "Penukaran: {$prize->name}",
            ]);

            return ['redemption' => $redemption];
        });

        if (isset($result['error'])) {
            throw new ApiResponseException($result['error']['message'], $result['error']['code']);
        }

        return $result['redemption']->load('prize');
    }

    public function paginateForUser(mixed $userId, mixed $status, int $perPage): LengthAwarePaginator
    {
        return $this->repository->paginateForUser($userId, $status, $perPage);
    }

    public function findOwned(mixed $userId, mixed $id, mixed $withPrize = true): Redemption
    {
        $model = $withPrize ? $this->repository->findWithPrize($id) : $this->repository->findById($id);

        if (! $model) {
            throw new ApiResponseException('Penukaran tidak ditemukan.', 404);
        }

        if ($model->loyalty_user_id !== $userId) {
            throw new ApiResponseException('Anda tidak memiliki akses ke penukaran ini.', 403);
        }

        return $model;
    }

    public function confirmDelivered(mixed $userId, mixed $id): Redemption
    {
        $model = $this->findOwned($userId, $id, false);

        if (! $model->canBeDelivered()) {
            throw new ApiResponseException(
                'Penukaran ini belum berstatus dikirim, tidak dapat dikonfirmasi diterima.',
                409,
                ['status' => $model->status],
            );
        }

        $model->update([
            'status' => Redemption::STATUS_DELIVERED,
            'delivered_at' => now(),
        ]);

        return $model->load('prize');
    }
}
