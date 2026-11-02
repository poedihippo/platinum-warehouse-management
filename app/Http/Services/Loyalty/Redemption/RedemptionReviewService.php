<?php

namespace App\Http\Services\Loyalty\Redemption;

use App\Exceptions\ApiResponseException;
use App\Http\Services\BaseService;
use App\Interfaces\Repositories\Loyalty\PointsTransaction\PointsTransactionRepositoryInterface;
use App\Interfaces\Repositories\Loyalty\Prize\PrizeRepositoryInterface;
use App\Interfaces\Repositories\Loyalty\Redemption\RedemptionRepositoryInterface;
use App\Interfaces\Services\Loyalty\Redemption\RedemptionReviewServiceInterface;
use App\Mail\Loyalty\RedemptionApprovedMail;
use App\Mail\Loyalty\RedemptionRejectedMail;
use App\Mail\Loyalty\RedemptionShippedMail;
use App\Models\Loyalty\PointsTransaction;
use App\Models\Loyalty\Redemption;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RedemptionReviewService extends BaseService implements RedemptionReviewServiceInterface
{
    public function __construct(
        protected RedemptionRepositoryInterface $repository,
        protected PrizeRepositoryInterface $prizeRepository,
        protected PointsTransactionRepositoryInterface $pointsRepository,
    ) {
        parent::__construct($repository);
    }

    public function paginate(mixed $status, mixed $search, mixed $sort, int $perPage): LengthAwarePaginator
    {
        return $this->repository->paginateForReview($status, $search, $sort, $perPage);
    }

    public function findForReview(mixed $id): ?Redemption
    {
        return $this->repository->findForReview($id);
    }

    public function approve(mixed $id, mixed $adminId): Redemption
    {
        $model = $this->findOrFail($id);
        if (! $model->canBeReviewed()) {
            $this->conflict($model);
        }

        $model->update([
            'status' => Redemption::STATUS_APPROVED,
            'reviewed_at' => now(),
            'reviewed_by' => $adminId,
        ]);

        Log::info('loyalty.redemption.approved', [
            'redemption_id' => $model->id,
            'admin_user_id' => $adminId,
        ]);

        $customer = $model->loyaltyUser;
        Mail::to($customer->email)->send(new RedemptionApprovedMail(
            $customer->name,
            $model->prize?->name ?? 'Hadiah',
        ));

        return $model->load(RedemptionRepositoryInterface::REVIEW_EAGER_LOAD);
    }

    /**
     * Rejecting restores the prize stock and refunds the points as an earn
     * entry sourced from the redemption.
     */
    public function reject(mixed $id, mixed $adminId, mixed $reason): Redemption
    {
        $model = $this->findOrFail($id);
        if (! $model->canBeReviewed()) {
            $this->conflict($model);
        }

        DB::transaction(function () use ($model, $adminId, $reason) {
            $prize = $this->prizeRepository->lockForUpdate($model->prize_id);
            if ($prize) {
                $prize->increment('stock');
            }

            $this->pointsRepository->create([
                'loyalty_user_id' => $model->loyalty_user_id,
                'direction' => PointsTransaction::DIRECTION_EARN,
                'amount' => $model->points_spent,
                'source_type' => PointsTransaction::SOURCE_REDEMPTION,
                'source_id' => $model->id,
                'description' => 'Pengembalian poin: '.($prize?->name ?? 'penukaran ditolak'),
            ]);

            $model->update([
                'status' => Redemption::STATUS_REJECTED,
                'rejection_reason' => $reason,
                'reviewed_at' => now(),
                'reviewed_by' => $adminId,
            ]);
        });

        Log::info('loyalty.redemption.rejected', [
            'redemption_id' => $model->id,
            'admin_user_id' => $adminId,
        ]);

        $customer = $model->loyaltyUser;
        Mail::to($customer->email)->send(new RedemptionRejectedMail(
            $customer->name,
            $model->prize?->name ?? 'Hadiah',
            $reason,
        ));

        return $model->load(RedemptionRepositoryInterface::REVIEW_EAGER_LOAD);
    }

    public function ship(mixed $id, mixed $adminId, mixed $trackingNumber, mixed $shippingCarrier): Redemption
    {
        $model = $this->findOrFail($id);
        if (! $model->canBeShipped()) {
            $this->conflict($model);
        }

        $model->update([
            'status' => Redemption::STATUS_SHIPPED,
            'tracking_number' => $trackingNumber,
            'shipping_carrier' => $shippingCarrier,
            'shipped_at' => now(),
        ]);

        Log::info('loyalty.redemption.shipped', [
            'redemption_id' => $model->id,
            'admin_user_id' => $adminId,
        ]);

        $customer = $model->loyaltyUser;
        Mail::to($customer->email)->send(new RedemptionShippedMail(
            $customer->name,
            $model->prize?->name ?? 'Hadiah',
            $model->tracking_number,
            $model->shipping_carrier,
        ));

        return $model->load(RedemptionRepositoryInterface::REVIEW_EAGER_LOAD);
    }

    public function deliver(mixed $id, mixed $adminId): Redemption
    {
        $model = $this->findOrFail($id);
        if (! $model->canBeDelivered()) {
            $this->conflict($model);
        }

        $model->update([
            'status' => Redemption::STATUS_DELIVERED,
            'delivered_at' => now(),
        ]);

        Log::info('loyalty.redemption.delivered', [
            'redemption_id' => $model->id,
            'admin_user_id' => $adminId,
        ]);

        return $model->load(RedemptionRepositoryInterface::REVIEW_EAGER_LOAD);
    }

    private function findOrFail(mixed $id): Redemption
    {
        $model = $this->repository->findById($id);
        abort_if(! $model, 404, 'Penukaran tidak ditemukan.');

        return $model;
    }

    private function conflict(Redemption $model): never
    {
        throw new ApiResponseException('Status penukaran tidak memungkinkan aksi ini.', 409, ['status' => $model->status]);
    }
}
