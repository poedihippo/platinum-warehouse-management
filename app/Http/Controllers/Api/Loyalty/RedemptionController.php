<?php

namespace App\Http\Controllers\Api\Loyalty;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Loyalty\RedemptionStoreRequest;
use App\Http\Resources\Loyalty\RedemptionResource;
use App\Models\Loyalty\PointsTransaction;
use App\Models\Loyalty\Prize;
use App\Models\Loyalty\Redemption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RedemptionController extends Controller
{
    /**
     * POST /api/loyalty/redemptions
     *
     * Atomic redeem: lock the prize row, verify stock + balance, decrement
     * stock, write the redemption and the matching 'spend' ledger row, all
     * in one transaction so a partial failure can never drift the balance.
     */
    public function store(RedemptionStoreRequest $request)
    {
        $user = $request->user();
        $prizeId = $request->input('prize_id');

        // Anti-double-tap: refuse a second pending redemption of the same
        // prize by the same customer (best-effort; the balance check is the
        // hard guarantee against overspending).
        $hasPending = Redemption::where('loyalty_user_id', $user->getKey())
            ->where('prize_id', $prizeId)
            ->where('status', Redemption::STATUS_PENDING)
            ->exists();

        if ($hasPending) {
            return response()->json([
                'message' => 'Anda masih memiliki penukaran hadiah ini yang sedang diproses.',
            ], 409);
        }

        $result = DB::transaction(function () use ($user, $prizeId, $request) {
            /** @var Prize|null $prize */
            $prize = Prize::lockForUpdate()->find($prizeId);

            // Inactive prizes are not peekable by id (same as the catalog
            // detail endpoint): treat them as not found rather than leaking
            // their existence.
            if (!$prize || !$prize->is_active) {
                return ['error' => ['message' => 'Hadiah tidak ditemukan.', 'code' => 404]];
            }

            if ($prize->stock <= 0) {
                return ['error' => ['message' => 'Hadiah sudah habis.', 'code' => 422]];
            }

            $balance = $this->balanceFor($user->getKey());
            if ($balance < $prize->points_cost) {
                return ['error' => ['message' => 'Poin tidak cukup.', 'code' => 422]];
            }

            $prize->decrement('stock');

            $redemption = Redemption::create([
                'loyalty_user_id' => $user->getKey(),
                'prize_id' => $prize->id,
                'points_spent' => $prize->points_cost,
                'quantity' => 1,
                'status' => Redemption::STATUS_PENDING,
                'recipient_name' => $request->input('recipient_name'),
                'recipient_phone' => $request->input('recipient_phone'),
                'recipient_address' => $request->input('recipient_address'),
                'recipient_notes' => $request->input('recipient_notes'),
                'submitted_at' => now(),
            ]);

            PointsTransaction::create([
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
            return response()->json(['message' => $result['error']['message']], $result['error']['code']);
        }

        return (new RedemptionResource($result['redemption']->load('prize')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/loyalty/redemptions — own redemptions, newest first, 15/page.
     */
    public function index(Request $request)
    {
        $query = Redemption::with('prize')
            ->where('loyalty_user_id', $request->user()->getKey())
            ->orderByDesc('submitted_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return RedemptionResource::collection($query->paginate(15));
    }

    /**
     * GET /api/loyalty/redemptions/{redemption} — single own redemption.
     * 403 if it belongs to another customer.
     */
    public function show(Request $request, string $redemption)
    {
        $model = Redemption::with('prize')->find($redemption);

        if (!$model) {
            return response()->json(['message' => 'Penukaran tidak ditemukan.'], 404);
        }

        if ($model->loyalty_user_id !== $request->user()->getKey()) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke penukaran ini.'], 403);
        }

        return new RedemptionResource($model);
    }

    /**
     * Spendable balance = earned - spent, derived from the ledger
     * (spec §5.9). Mirrors PointsController::balance().
     */
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
}
