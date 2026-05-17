<?php

namespace App\Models\Loyalty;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer's request to exchange points for a prize.
 *
 * Status state machine (spec §6.2): pending -> shipped -> delivered,
 * or pending -> cancelled (cancel writes a refund transaction).
 *
 * Customer/admin redemption endpoints are out of Phase 1 scope; the
 * model exists so points-balance math over 'spend' transactions is
 * complete and testable.
 */
class Redemption extends Model
{
    use HasFactory, HasUlids;

    protected $guarded = [];

    protected $casts = [
        'point_cost' => 'integer',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function loyaltyUser(): BelongsTo
    {
        return $this->belongsTo(LoyaltyUser::class);
    }

    public function prize(): BelongsTo
    {
        return $this->belongsTo(Prize::class);
    }
}
