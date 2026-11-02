<?php

namespace App\Http\Repositories\Loyalty\Claim;

use App\Http\Repositories\BaseRepository;
use App\Interfaces\Repositories\Loyalty\Claim\ClaimRepositoryInterface;
use App\Models\Loyalty\Claim;
use App\Models\Loyalty\PointsTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ClaimRepository extends BaseRepository implements ClaimRepositoryInterface
{
    public function __construct(Claim $model)
    {
        parent::__construct($model);
    }

    public function existsForUserInvoice(mixed $userId, mixed $invoiceNumber): bool
    {
        return Claim::where('loyalty_user_id', $userId)
            ->where('invoice_number', $invoiceNumber)
            ->exists();
    }

    public function pendingTotalForUser(mixed $userId): int
    {
        return (int) Claim::where('loyalty_user_id', $userId)
            ->where('status', 'pending')
            ->sum('total_points');
    }

    public function paginateForUser(mixed $userId, mixed $status, int $perPage): LengthAwarePaginator
    {
        $query = Claim::where('loyalty_user_id', $userId)
            ->orderByDesc('submitted_at');

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->paginate($perPage);
    }

    public function findForUser(mixed $userId, mixed $id): ?Claim
    {
        return Claim::with(['photos', 'lineItems'])
            ->where('loyalty_user_id', $userId)
            ->where('id', $id)
            ->first();
    }

    /**
     * Customer context shown on every admin claim payload: claim count and
     * lifetime earned points, without loading the whole ledger.
     */
    public function reviewEagerLoad(): array
    {
        return [
            'loyaltyUser' => fn ($q) => $q
                ->withCount('claims')
                ->withSum([
                    'pointsTransactions as total_points_earned' => fn ($t) => $t
                        ->where('direction', PointsTransaction::DIRECTION_EARN),
                ], 'amount'),
        ];
    }

    public function paginateForReview(mixed $status, mixed $search, mixed $sort, int $perPage): LengthAwarePaginator
    {
        $query = Claim::with($this->reviewEagerLoad())
            ->withCount(['photos', 'lineItems']);

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($search !== null) {
            $query->where(function ($sub) use ($search) {
                $sub->where('invoice_number', 'like', "%$search%")
                    ->orWhereHas('loyaltyUser', function ($q) use ($search) {
                        $q->where('name', 'like', "%$search%")->orWhere('email', 'like', "%$search%");
                    });
            });
        }

        match ($sort) {
            'submitted_at_asc' => $query->orderBy('submitted_at', 'asc'),
            'invoice_number_asc' => $query->orderBy('invoice_number', 'asc'),
            'invoice_number_desc' => $query->orderBy('invoice_number', 'desc'),
            default => $query->orderBy('submitted_at', 'desc'),
        };

        return $query->paginate($perPage);
    }

    public function findForReview(mixed $id): ?Claim
    {
        return Claim::with(array_merge($this->reviewEagerLoad(), [
            'photos',
            'lineItems.productUnit',
        ]))->find($id);
    }

    public function duplicateWarnings(Claim $claim): Collection
    {
        return Claim::with('loyaltyUser:id,email')
            ->where('invoice_number', $claim->invoice_number)
            ->where('loyalty_user_id', '!=', $claim->loyalty_user_id)
            ->get()
            ->map(fn (Claim $c) => [
                'claim_id' => $c->id,
                'user_email' => $c->loyaltyUser?->email,
                'submitted_at' => $c->submitted_at?->toIso8601String(),
                'status' => $c->status,
            ])
            ->values();
    }

    public function lockForUpdate(Claim $claim): ?Claim
    {
        return Claim::whereKey($claim->getKey())->lockForUpdate()->first();
    }
}
