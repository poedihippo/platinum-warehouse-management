<?php

namespace App\Models\Loyalty;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only points ledger row. Balance is always derived from this
 * table (spec §5.9); never store a balance column.
 *
 * source_type ('claim' | 'redemption') + source_id form an
 * application-level polymorphic pointer. Intentionally NOT morphTo:
 * the two source models are in different namespaces and we never want
 * a relation cascade to touch the ledger.
 */
class PointsTransaction extends Model
{
    use HasFactory, HasUlids;

    // Table has no updated_at column; let Eloquent manage created_at only.
    const UPDATED_AT = null;

    public const DIRECTION_EARN = 'earn';
    public const DIRECTION_SPEND = 'spend';

    public const SOURCE_CLAIM = 'claim';
    public const SOURCE_REDEMPTION = 'redemption';

    protected $guarded = [];

    protected $casts = [
        'amount' => 'integer',
    ];

    public function loyaltyUser(): BelongsTo
    {
        return $this->belongsTo(LoyaltyUser::class);
    }
}
