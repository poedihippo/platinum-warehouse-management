<?php

namespace App\Http\Services\Loyalty\Claim;

use App\Exceptions\ApiResponseException;
use App\Http\Services\BaseService;
use App\Interfaces\Repositories\Loyalty\Claim\ClaimRepositoryInterface;
use App\Interfaces\Repositories\Loyalty\PointsTransaction\PointsTransactionRepositoryInterface;
use App\Interfaces\Repositories\ProductUnit\ProductUnitRepositoryInterface;
use App\Interfaces\Services\Loyalty\Claim\ClaimReviewServiceInterface;
use App\Mail\Loyalty\ClaimApprovedMail;
use App\Mail\Loyalty\ClaimRejectedMail;
use App\Models\Loyalty\Claim;
use App\Models\Loyalty\ClaimLineItem;
use App\Models\Loyalty\PointsTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ClaimReviewService extends BaseService implements ClaimReviewServiceInterface
{
    public function __construct(
        protected ClaimRepositoryInterface $repository,
        protected PointsTransactionRepositoryInterface $pointsRepository,
        protected ProductUnitRepositoryInterface $productUnitRepository,
    ) {
        parent::__construct($repository);
    }

    public function paginate(?string $status, ?string $search, ?string $sort, int $perPage): LengthAwarePaginator
    {
        return $this->repository->paginateForReview($status, $search, $sort, $perPage);
    }

    public function findForReview(string $id): ?Claim
    {
        $model = $this->repository->findForReview($id);

        if ($model) {
            $model->duplicate_warnings = $this->repository->duplicateWarnings($model);
        }

        return $model;
    }

    public function addLineItem(string $claimId, mixed $productUnitId, mixed $quantity): ClaimLineItem
    {
        $model = $this->findOrFail($claimId);
        $this->ensurePending($model);

        $productUnit = $this->productUnitRepository->findById($productUnitId);
        if (! $productUnit || (int) $productUnit->points_per_unit <= 0) {
            throw new ApiResponseException('Product unit ini tidak memiliki nilai poin (points_per_unit harus > 0).', 422);
        }

        return $model->lineItems()->create([
            'product_unit_id' => $productUnit->id,
            'quantity' => (int) $quantity,
            'points_awarded' => 0,
        ]);
    }

    public function updateLineItem(string $claimId, string $lineItemId, mixed $quantity): ClaimLineItem
    {
        $model = $this->findOrFail($claimId);
        $this->ensurePending($model);

        $item = $this->findLineItem($model, $lineItemId);

        $quantity = (int) $quantity;
        $pointsPerUnit = (int) ($item->productUnit?->points_per_unit ?? 0);

        $item->update([
            'quantity' => $quantity,
            'points_awarded' => $quantity * $pointsPerUnit,
        ]);

        return $item;
    }

    public function removeLineItem(string $claimId, string $lineItemId): void
    {
        $model = $this->findOrFail($claimId);
        $this->ensurePending($model);

        $this->findLineItem($model, $lineItemId)->delete();
    }

    /**
     * Points are recomputed from the live points_per_unit under a row lock, so a
     * concurrent second approval sees the claim as already reviewed.
     */
    public function approve(string $claimId, mixed $adminId): Claim
    {
        $model = $this->findOrFail($claimId);
        $this->ensurePending($model, 409);

        if ($model->lineItems()->count() === 0) {
            throw new ApiResponseException('Tidak dapat menyetujui klaim tanpa line item.', 422);
        }

        $claimModel = DB::transaction(function () use ($model, $adminId) {
            $locked = $this->repository->lockForUpdate($model);

            if ($locked->status !== 'pending') {
                return $locked;
            }

            $total = 0;
            foreach ($locked->lineItems()->with('productUnit')->get() as $item) {
                $pointsPerUnit = (int) ($item->productUnit?->points_per_unit ?? 0);
                $awarded = $item->quantity * $pointsPerUnit;
                $item->update(['points_awarded' => $awarded]);
                $total += $awarded;
            }

            $locked->update([
                'status' => 'approved',
                'reviewed_at' => now(),
                'reviewed_by' => $adminId,
                'total_points' => $total,
            ]);

            $this->pointsRepository->create([
                'loyalty_user_id' => $locked->loyalty_user_id,
                'direction' => PointsTransaction::DIRECTION_EARN,
                'amount' => $total,
                'source_type' => PointsTransaction::SOURCE_CLAIM,
                'source_id' => $locked->id,
                'description' => "Invoice {$locked->invoice_number}",
            ]);

            return $locked;
        });

        if ($claimModel->status === 'approved' && $claimModel->reviewed_by !== $adminId) {
            throw new ApiResponseException('Klaim sudah direview.', 409, ['status' => $claimModel->status]);
        }

        Log::info('loyalty.claim.approved', [
            'claim_id' => $claimModel->id,
            'admin_user_id' => $adminId,
            'total_points' => $claimModel->total_points,
        ]);

        $customer = $claimModel->loyaltyUser;
        Mail::to($customer->email)->send(new ClaimApprovedMail(
            $customer->name,
            $claimModel->invoice_number,
            (int) $claimModel->total_points,
        ));

        return $claimModel->load(array_merge($this->repository->reviewEagerLoad(), ['photos', 'lineItems.productUnit']));
    }

    public function reject(string $claimId, mixed $adminId, mixed $reason): Claim
    {
        $model = $this->findOrFail($claimId);
        $this->ensurePending($model, 409);

        $model->update([
            'status' => 'rejected',
            'reviewed_at' => now(),
            'reviewed_by' => $adminId,
            'rejection_reason' => $reason,
        ]);

        Log::info('loyalty.claim.rejected', [
            'claim_id' => $model->id,
            'admin_user_id' => $adminId,
        ]);

        $customer = $model->loyaltyUser;
        Mail::to($customer->email)->send(new ClaimRejectedMail(
            $customer->name,
            $model->invoice_number,
            $reason,
        ));

        return $model->load(array_merge($this->repository->reviewEagerLoad(), ['photos', 'lineItems.productUnit']));
    }

    private function findOrFail(string $claimId): Claim
    {
        $model = $this->repository->findById($claimId);
        abort_if(! $model, 404, 'Klaim tidak ditemukan.');

        return $model;
    }

    private function findLineItem(Claim $claim, string $lineItemId): ClaimLineItem
    {
        $item = $claim->lineItems()->where('id', $lineItemId)->first();

        if (! $item) {
            throw new ApiResponseException('Line item tidak ditemukan.', 404);
        }

        return $item;
    }

    private function ensurePending(Claim $claim, int $code = 422): void
    {
        if ($claim->status === 'pending') {
            return;
        }

        throw new ApiResponseException(
            $code === 409 ? 'Klaim sudah direview.' : 'Klaim tidak lagi berstatus pending.',
            $code,
            ['status' => $claim->status],
        );
    }
}
