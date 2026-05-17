<?php

namespace App\Models\Loyalty;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single invoice submission by a loyalty customer.
 *
 * Status state machine (spec §6.1): pending -> approved | rejected.
 * Both approved and rejected are terminal; no undo.
 */
class Claim extends Model
{
    use HasFactory, HasUlids;

    protected $guarded = [];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'total_points' => 'integer',
    ];

    public function loyaltyUser(): BelongsTo
    {
        return $this->belongsTo(LoyaltyUser::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ClaimPhoto::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(ClaimLineItem::class);
    }

    /**
     * The warehouse/admin user who reviewed this claim. Nullable until
     * reviewed. Points at App\Models\User (bigint PK), not a loyalty user.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
