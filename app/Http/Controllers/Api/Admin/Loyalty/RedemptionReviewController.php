<?php

namespace App\Http\Controllers\Api\Admin\Loyalty;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Loyalty\Admin\RejectRedemptionRequest;
use App\Http\Requests\Api\Loyalty\Admin\ShipRedemptionRequest;
use App\Http\Resources\Loyalty\AdminRedemptionResource;
use App\Mail\Loyalty\RedemptionApprovedMail;
use App\Mail\Loyalty\RedemptionRejectedMail;
use App\Mail\Loyalty\RedemptionShippedMail;
use App\Models\Loyalty\PointsTransaction;
use App\Models\Loyalty\Prize;
use App\Models\Loyalty\Redemption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RedemptionReviewController extends Controller
{
    private const PERMISSION = 'review redemptions';

    /**
     * GET /api/admin/loyalty/redemptions?status=pending&sort=submitted_at_desc
     */
    public function index(Request $request)
    {
        if ($denied = $this->denyUnlessAuthorized($request)) {
            return $denied;
        }

        $query = Redemption::with([
            'loyaltyUser:id,name,email,phone',
            'prize:id,name,photo_path',
        ]);

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        match ($request->input('sort')) {
            'submitted_at_asc' => $query->orderBy('submitted_at', 'asc'),
            default => $query->orderBy('submitted_at', 'desc'),
        };

        $perPage = (int) $request->input('per_page', 15);
        $perPage = $perPage > 0 && $perPage <= 100 ? $perPage : 15;

        return AdminRedemptionResource::collection($query->paginate($perPage));
    }

    /**
     * GET /api/admin/loyalty/redemptions/{redemption}
     */
    public function show(Request $request, string $redemption)
    {
        if ($denied = $this->denyUnlessAuthorized($request)) {
            return $denied;
        }

        $model = Redemption::with([
            'loyaltyUser:id,name,email,phone',
            'prize:id,name,photo_path',
        ])->find($redemption);

        if (!$model) {
            return response()->json(['message' => 'Penukaran tidak ditemukan.'], 404);
        }

        return new AdminRedemptionResource($model);
    }

    /**
     * POST /api/admin/loyalty/redemptions/{redemption}/approve
     * pending -> approved. 409 if not pending.
     */
    public function approve(Request $request, string $redemption)
    {
        if ($denied = $this->denyUnlessAuthorized($request)) {
            return $denied;
        }

        $model = $this->findOrFail($redemption);
        if (!$model->canBeReviewed()) {
            return $this->conflict($model);
        }

        $model->update([
            'status' => Redemption::STATUS_APPROVED,
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->getKey(),
        ]);

        Log::info('loyalty.redemption.approved', [
            'redemption_id' => $model->id,
            'admin_user_id' => $request->user()->getKey(),
        ]);

        $customer = $model->loyaltyUser;
        Mail::to($customer->email)->send(new RedemptionApprovedMail(
            $customer->name,
            $model->prize?->name ?? 'Hadiah',
        ));

        return new AdminRedemptionResource(
            $model->load(['loyaltyUser:id,name,email,phone', 'prize:id,name,photo_path'])
        );
    }

    /**
     * POST /api/admin/loyalty/redemptions/{redemption}/reject
     * pending -> rejected. Restores stock + refunds points. 409 if not pending.
     */
    public function reject(RejectRedemptionRequest $request, string $redemption)
    {
        if ($denied = $this->denyUnlessAuthorized($request)) {
            return $denied;
        }

        $model = $this->findOrFail($redemption);
        if (!$model->canBeReviewed()) {
            return $this->conflict($model);
        }

        $adminId = $request->user()->getKey();
        $reason = $request->input('reason');

        DB::transaction(function () use ($model, $adminId, $reason) {
            // Restore stock under a row lock to avoid clobbering a
            // concurrent redeem's decrement.
            $prize = Prize::lockForUpdate()->find($model->prize_id);
            if ($prize) {
                $prize->increment('stock');
            }

            PointsTransaction::create([
                'loyalty_user_id' => $model->loyalty_user_id,
                'direction' => PointsTransaction::DIRECTION_EARN,
                'amount' => $model->points_spent,
                'source_type' => PointsTransaction::SOURCE_REDEMPTION,
                'source_id' => $model->id,
                'description' => 'Pengembalian poin: ' . ($prize?->name ?? 'penukaran ditolak'),
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

        return new AdminRedemptionResource(
            $model->load(['loyaltyUser:id,name,email,phone', 'prize:id,name,photo_path'])
        );
    }

    /**
     * POST /api/admin/loyalty/redemptions/{redemption}/ship
     * approved -> shipped. 409 otherwise.
     */
    public function ship(ShipRedemptionRequest $request, string $redemption)
    {
        if ($denied = $this->denyUnlessAuthorized($request)) {
            return $denied;
        }

        $model = $this->findOrFail($redemption);
        if (!$model->canBeShipped()) {
            return $this->conflict($model);
        }

        $model->update([
            'status' => Redemption::STATUS_SHIPPED,
            'tracking_number' => $request->input('tracking_number'),
            'shipping_carrier' => $request->input('shipping_carrier'),
            'shipped_at' => now(),
        ]);

        Log::info('loyalty.redemption.shipped', [
            'redemption_id' => $model->id,
            'admin_user_id' => $request->user()->getKey(),
        ]);

        $customer = $model->loyaltyUser;
        Mail::to($customer->email)->send(new RedemptionShippedMail(
            $customer->name,
            $model->prize?->name ?? 'Hadiah',
            $model->tracking_number,
            $model->shipping_carrier,
        ));

        return new AdminRedemptionResource(
            $model->load(['loyaltyUser:id,name,email,phone', 'prize:id,name,photo_path'])
        );
    }

    /**
     * POST /api/admin/loyalty/redemptions/{redemption}/deliver
     * shipped -> delivered. Terminal. No email (per spec).
     */
    public function deliver(Request $request, string $redemption)
    {
        if ($denied = $this->denyUnlessAuthorized($request)) {
            return $denied;
        }

        $model = $this->findOrFail($redemption);
        if (!$model->canBeDelivered()) {
            return $this->conflict($model);
        }

        $model->update([
            'status' => Redemption::STATUS_DELIVERED,
            'delivered_at' => now(),
        ]);

        Log::info('loyalty.redemption.delivered', [
            'redemption_id' => $model->id,
            'admin_user_id' => $request->user()->getKey(),
        ]);

        return new AdminRedemptionResource(
            $model->load(['loyaltyUser:id,name,email,phone', 'prize:id,name,photo_path'])
        );
    }

    private function findOrFail(string $redemption): Redemption
    {
        $model = Redemption::find($redemption);
        abort_if(!$model, 404, 'Penukaran tidak ditemukan.');

        return $model;
    }

    private function conflict(Redemption $model)
    {
        return response()->json([
            'message' => 'Status penukaran tidak memungkinkan aksi ini.',
            'status' => $model->status,
        ], 409);
    }

    private function denyUnlessAuthorized(Request $request)
    {
        if ($request->user()?->can(self::PERMISSION)) {
            return null;
        }

        return response()->json([
            'message' => 'Anda tidak memiliki izin untuk meninjau penukaran.',
        ], 403);
    }
}
