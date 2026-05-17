<?php

namespace App\Http\Controllers\Api\Admin\Loyalty;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Loyalty\Admin\AddLineItemRequest;
use App\Http\Requests\Api\Loyalty\Admin\RejectClaimRequest;
use App\Http\Resources\Loyalty\AdminClaimResource;
use App\Mail\Loyalty\ClaimApprovedMail;
use App\Mail\Loyalty\ClaimRejectedMail;
use App\Models\Loyalty\Claim;
use App\Models\Loyalty\PointsTransaction;
use App\Models\ProductUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ClaimReviewController extends Controller
{
    /**
     * GET /api/admin/loyalty/claims?status=pending
     * Queue ordered oldest-first, 20/page.
     */
    public function index(Request $request)
    {
        $query = Claim::with('loyaltyUser')
            ->orderBy('submitted_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return AdminClaimResource::collection($query->paginate(20));
    }

    /**
     * GET /api/admin/loyalty/claims/{claim}
     * Full detail + cross-user duplicate invoice warnings (§9.1).
     */
    public function show(string $claim)
    {
        $model = Claim::with([
            'loyaltyUser',
            'photos',
            'lineItems.productUnit',
        ])->find($claim);

        if (!$model) {
            return response()->json(['message' => 'Klaim tidak ditemukan.'], 404);
        }

        $model->duplicate_warnings = Claim::with('loyaltyUser:id,email')
            ->where('invoice_number', $model->invoice_number)
            ->where('loyalty_user_id', '!=', $model->loyalty_user_id)
            ->get()
            ->map(fn (Claim $c) => [
                'claim_id' => $c->id,
                'user_email' => $c->loyaltyUser?->email,
                'submitted_at' => $c->submitted_at?->toIso8601String(),
                'status' => $c->status,
            ])
            ->values();

        return new AdminClaimResource($model);
    }

    /**
     * POST /api/admin/loyalty/claims/{claim}/line-items
     */
    public function addLineItem(AddLineItemRequest $request, string $claim)
    {
        $model = $this->findOrFail($claim);
        if ($response = $this->ensurePending($model)) {
            return $response;
        }

        $productUnit = ProductUnit::find($request->input('product_unit_id'));
        if (!$productUnit || (int) $productUnit->points_per_unit <= 0) {
            return response()->json([
                'message' => 'Product unit ini tidak memiliki nilai poin (points_per_unit harus > 0).',
            ], 422);
        }

        // points_awarded stays 0 until approval, when it is captured
        // from points_per_unit at that moment.
        $lineItem = $model->lineItems()->create([
            'product_unit_id' => $productUnit->id,
            'quantity' => (int) $request->input('quantity'),
            'points_awarded' => 0,
        ]);

        return response()->json([
            'message' => 'Line item ditambahkan.',
            'data' => [
                'id' => $lineItem->id,
                'product_unit_id' => $lineItem->product_unit_id,
                'quantity' => (int) $lineItem->quantity,
            ],
        ], 201);
    }

    /**
     * DELETE /api/admin/loyalty/claims/{claim}/line-items/{lineItem}
     */
    public function removeLineItem(string $claim, string $lineItem)
    {
        $model = $this->findOrFail($claim);
        if ($response = $this->ensurePending($model)) {
            return $response;
        }

        $item = $model->lineItems()->where('id', $lineItem)->first();
        if (!$item) {
            return response()->json(['message' => 'Line item tidak ditemukan.'], 404);
        }

        $item->delete();

        return response()->json(['message' => 'Line item dihapus.']);
    }

    /**
     * POST /api/admin/loyalty/claims/{claim}/approve
     *
     * Atomic: lock the claim, capture per-line points, write the claim,
     * write the earn ledger row. Idempotent-safe: a non-pending claim
     * returns 409 with its current status (no double-approval).
     */
    public function approve(Request $request, string $claim)
    {
        $model = $this->findOrFail($claim);
        if ($response = $this->ensurePending($model, 409)) {
            return $response;
        }

        if ($model->lineItems()->count() === 0) {
            return response()->json([
                'message' => 'Tidak dapat menyetujui klaim tanpa line item.',
            ], 422);
        }

        $adminId = $request->user()->getKey();

        $claimModel = DB::transaction(function () use ($model, $adminId) {
            /** @var Claim $locked */
            $locked = Claim::whereKey($model->getKey())->lockForUpdate()->first();

            // Re-check under the row lock to close the double-approve race.
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

            PointsTransaction::create([
                'loyalty_user_id' => $locked->loyalty_user_id,
                'direction' => PointsTransaction::DIRECTION_EARN,
                'amount' => $total,
                'source_type' => PointsTransaction::SOURCE_CLAIM,
                'source_id' => $locked->id,
                'description' => "Invoice {$locked->invoice_number}",
            ]);

            return $locked;
        });

        // Lost the race to a concurrent approval.
        if ($claimModel->status === 'approved' && $claimModel->reviewed_by !== $adminId) {
            return response()->json([
                'message' => 'Klaim sudah direview.',
                'status' => $claimModel->status,
            ], 409);
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

        return new AdminClaimResource(
            $claimModel->load(['loyaltyUser', 'photos', 'lineItems.productUnit'])
        );
    }

    /**
     * POST /api/admin/loyalty/claims/{claim}/reject
     * No points transaction. Idempotent-safe (409 if not pending).
     */
    public function reject(RejectClaimRequest $request, string $claim)
    {
        $model = $this->findOrFail($claim);
        if ($response = $this->ensurePending($model, 409)) {
            return $response;
        }

        $adminId = $request->user()->getKey();

        $model->update([
            'status' => 'rejected',
            'reviewed_at' => now(),
            'reviewed_by' => $adminId,
            'rejection_reason' => $request->input('reason'),
        ]);

        Log::info('loyalty.claim.rejected', [
            'claim_id' => $model->id,
            'admin_user_id' => $adminId,
        ]);

        $customer = $model->loyaltyUser;
        Mail::to($customer->email)->send(new ClaimRejectedMail(
            $customer->name,
            $model->invoice_number,
            $request->input('reason'),
        ));

        return new AdminClaimResource(
            $model->load(['loyaltyUser', 'photos', 'lineItems.productUnit'])
        );
    }

    private function findOrFail(string $claim): Claim
    {
        $model = Claim::find($claim);
        abort_if(!$model, 404, 'Klaim tidak ditemukan.');

        return $model;
    }

    /**
     * Returns a JSON response if the claim is not pending, else null.
     * Line-item ops use 422; approve/reject use 409 (idempotent-safe).
     */
    private function ensurePending(Claim $claim, int $code = 422)
    {
        if ($claim->status === 'pending') {
            return null;
        }

        return response()->json([
            'message' => $code === 409
                ? 'Klaim sudah direview.'
                : 'Klaim tidak lagi berstatus pending.',
            'status' => $claim->status,
        ], $code);
    }
}
