<?php

namespace App\Http\Controllers\Api\Loyalty;

use App\Http\Controllers\Controller;
use App\Http\Resources\Loyalty\PointsTransactionResource;
use App\Models\Loyalty\Claim;
use App\Models\Loyalty\PointsTransaction;
use Illuminate\Http\Request;

class PointsController extends Controller
{
    /**
     * GET /api/loyalty/points/balance
     *
     * - pending  = sum of total_points over the user's pending claims
     *              (no ledger rows exist until approval).
     * - approved = spendable balance = earned - spent, derived purely
     *              from the append-only ledger (spec §5.9). Never stored.
     */
    public function balance(Request $request)
    {
        $userId = $request->user()->getKey();

        $pending = (int) Claim::where('loyalty_user_id', $userId)
            ->where('status', 'pending')
            ->sum('total_points');

        $earned = (int) PointsTransaction::where('loyalty_user_id', $userId)
            ->where('direction', PointsTransaction::DIRECTION_EARN)
            ->sum('amount');

        $spent = (int) PointsTransaction::where('loyalty_user_id', $userId)
            ->where('direction', PointsTransaction::DIRECTION_SPEND)
            ->sum('amount');

        return response()->json([
            'pending' => $pending,
            'approved' => $earned - $spent,
        ]);
    }

    /**
     * GET /api/loyalty/points/transactions — ledger, newest first, 20/page.
     */
    public function transactions(Request $request)
    {
        $transactions = PointsTransaction::where('loyalty_user_id', $request->user()->getKey())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20);

        return PointsTransactionResource::collection($transactions);
    }
}
