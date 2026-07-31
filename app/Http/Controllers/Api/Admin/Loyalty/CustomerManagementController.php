<?php

namespace App\Http\Controllers\Api\Admin\Loyalty;

use App\Http\Controllers\Controller;
use App\Http\Resources\Loyalty\AdminCustomerListResource;
use App\Http\Resources\Loyalty\AdminCustomerResource;
use App\Models\Loyalty\LoyaltyUser;
use App\Models\Loyalty\PointsTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomerManagementController extends Controller
{
    private const PERMISSION = 'manage customers';

    /**
     * GET /api/admin/loyalty/customers?q=&is_active=&per_page=&sort=
     *
     * points_balance is computed via a correlated subquery over
     * points_transactions (never a stored column — spec §5.9), added as
     * a single query so the list stays N+1-free.
     */
    public function index(Request $request)
    {
        if ($denied = $this->denyUnlessAuthorized($request)) {
            return $denied;
        }

        $query = LoyaltyUser::query()->addSelect([
            'points_balance' => PointsTransaction::selectRaw(
                "COALESCE(SUM(CASE WHEN direction = 'earn' THEN amount ELSE -amount END), 0)"
            )->whereColumn('loyalty_user_id', 'loyalty_users.id'),
        ]);

        if ($request->filled('q')) {
            $value = $request->input('q');
            $query->where(function ($q) use ($value) {
                $q->where('name', 'like', "%$value%")
                    ->orWhere('email', 'like', "%$value%")
                    ->orWhere('phone', 'like', "%$value%");
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        match ($request->input('sort')) {
            'name_asc' => $query->orderBy('name'),
            'name_desc' => $query->orderByDesc('name'),
            'points_asc' => $query->orderBy('points_balance'),
            'points_desc' => $query->orderByDesc('points_balance'),
            default => $query->orderByDesc('created_at'),
        };

        $perPage = (int) $request->input('per_page', 15);
        $perPage = $perPage > 0 && $perPage <= 100 ? $perPage : 15;

        return AdminCustomerListResource::collection($query->paginate($perPage));
    }

    /**
     * GET /api/admin/loyalty/customers/{loyaltyUser}
     */
    public function show(Request $request, string $loyaltyUser)
    {
        if ($denied = $this->denyUnlessAuthorized($request)) {
            return $denied;
        }

        $model = LoyaltyUser::withCount(['claims', 'redemptions'])->find($loyaltyUser);
        if (!$model) {
            return response()->json(['message' => 'Pelanggan tidak ditemukan.'], 404);
        }

        $model->points_balance = $this->balanceFor($model->id);

        return new AdminCustomerResource($model);
    }

    /**
     * POST /api/admin/loyalty/customers/{loyaltyUser}/adjust-points
     *
     * Never edits a balance column — inserts a new points_transactions
     * row, same as every other earn/spend event. That row IS the audit
     * record (adjusted_by + reason). Blocks an adjustment that would
     * take the derived balance below zero.
     */
    public function adjustPoints(Request $request, string $loyaltyUser)
    {
        if ($denied = $this->denyUnlessAuthorized($request)) {
            return $denied;
        }

        $model = LoyaltyUser::find($loyaltyUser);
        if (!$model) {
            return response()->json(['message' => 'Pelanggan tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'points' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $points = (int) $validated['points'];
        $currentBalance = $this->balanceFor($model->id);

        if ($currentBalance + $points < 0) {
            return response()->json(['message' => 'Saldo tidak cukup.'], 422);
        }

        // source_id has no natural source entity for a manual adjustment
        // (unlike claim/redemption); pre-generate the row's own id so it
        // can point at itself, keeping the column NOT NULL without
        // inventing an unrelated FK.
        $transactionId = strtolower((string) Str::ulid());

        PointsTransaction::create([
            'id' => $transactionId,
            'loyalty_user_id' => $model->id,
            'direction' => $points > 0 ? PointsTransaction::DIRECTION_EARN : PointsTransaction::DIRECTION_SPEND,
            'amount' => abs($points),
            'source_type' => PointsTransaction::SOURCE_MANUAL_ADJUSTMENT,
            'source_id' => $transactionId,
            'description' => $validated['reason'],
            'adjusted_by' => $request->user()->getKey(),
            'reason' => $validated['reason'],
        ]);

        $model->points_balance = $this->balanceFor($model->id);
        $model->loadCount(['claims', 'redemptions']);

        return new AdminCustomerResource($model);
    }

    /**
     * PATCH /api/admin/loyalty/customers/{loyaltyUser}/toggle-active
     */
    public function toggleActive(Request $request, string $loyaltyUser)
    {
        if ($denied = $this->denyUnlessAuthorized($request)) {
            return $denied;
        }

        $model = LoyaltyUser::find($loyaltyUser);
        if (!$model) {
            return response()->json(['message' => 'Pelanggan tidak ditemukan.'], 404);
        }

        $model->update(['is_active' => !$model->is_active]);

        $model->points_balance = $this->balanceFor($model->id);
        $model->loadCount(['claims', 'redemptions']);

        return new AdminCustomerResource($model);
    }

    private function balanceFor(string $userId): int
    {
        $earned = (int) PointsTransaction::where('loyalty_user_id', $userId)
            ->where('direction', PointsTransaction::DIRECTION_EARN)
            ->sum('amount');

        $spent = (int) PointsTransaction::where('loyalty_user_id', $userId)
            ->where('direction', PointsTransaction::DIRECTION_SPEND)
            ->sum('amount');

        return $earned - $spent;
    }

    private function denyUnlessAuthorized(Request $request)
    {
        if ($request->user()?->can(self::PERMISSION)) {
            return null;
        }

        return response()->json([
            'message' => 'Anda tidak memiliki izin untuk mengelola pelanggan.',
        ], 403);
    }
}
