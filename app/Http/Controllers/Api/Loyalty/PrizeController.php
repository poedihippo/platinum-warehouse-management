<?php

namespace App\Http\Controllers\Api\Loyalty;

use App\Http\Controllers\Controller;
use App\Http\Resources\Loyalty\PrizeResource;
use App\Models\Loyalty\PointsTransaction;
use App\Models\Loyalty\Prize;
use Illuminate\Http\Request;

class PrizeController extends Controller
{
    /**
     * GET /api/loyalty/prizes
     *
     * Active prizes only. Sorted by points cost ascending by default.
     * affordable=true narrows to prizes the customer can currently afford.
     */
    public function index(Request $request)
    {
        $query = Prize::active()->with('category');

        if ($request->filled('q')) {
            $value = $request->input('q');
            $query->where(fn ($q) => $q->where('name', 'like', "%$value%"));
        }

        // prize_category_id=<id> narrows to that category (only matches when
        // the category is active/not deleted, same visibility rule as
        // PrizeResource). prize_category_id=none narrows to prizes with no
        // category, or whose category is inactive/deleted (customer-visible
        // "uncategorized" bucket).
        if ($request->filled('prize_category_id')) {
            $value = $request->input('prize_category_id');

            if ($value === 'none') {
                $query->where(function ($q) {
                    $q->whereNull('prize_category_id')
                        ->orWhereDoesntHave('category', fn ($c) => $c->active());
                });
            } else {
                $query->where('prize_category_id', $value)
                    ->whereHas('category', fn ($c) => $c->active());
            }
        }

        match ($request->input('sort')) {
            'points_desc' => $query->orderByDesc('points_cost'),
            'name_asc' => $query->orderBy('name'),
            'name_desc' => $query->orderByDesc('name'),
            default => $query->orderBy('points_cost'),
        };

        if ($request->boolean('affordable')) {
            $balance = $this->balanceFor($request->user()->getKey());
            $query->where('points_cost', '<=', $balance);
        }

        $perPage = (int) $request->input('per_page', 15);
        $perPage = $perPage > 0 && $perPage <= 100 ? $perPage : 15;

        return PrizeResource::collection($query->paginate($perPage));
    }

    /**
     * GET /api/loyalty/prizes/{prize}
     *
     * 404 on an inactive prize — hidden prizes are not peekable by id.
     */
    public function show(string $prize)
    {
        $model = Prize::active()->with('category')->find($prize);

        if (!$model) {
            return response()->json(['message' => 'Hadiah tidak ditemukan.'], 404);
        }

        return new PrizeResource($model);
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
