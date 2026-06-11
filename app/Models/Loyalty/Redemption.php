<?php

namespace App\Models\Loyalty;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer's request to exchange points for a prize.
 *
 * Status state machine (Phase 4):
 *   pending --approve--> approved --ship--> shipped --deliver--> delivered
 *   pending --reject--> rejected  (restores stock + refunds points)
 *
 * Terminal states: delivered, rejected.
 */
class Redemption extends Model
{
    use HasFactory, HasUlids;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'loyalty_user_id',
        'prize_id',
        'points_spent',
        'quantity',
        'status',
        'recipient_name',
        'recipient_phone',
        'recipient_address',
        'recipient_notes',
        'rejection_reason',
        'tracking_number',
        'shipping_carrier',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
        'shipped_at',
        'delivered_at',
    ];

    protected $casts = [
        'points_spent' => 'integer',
        'quantity' => 'integer',
        'status' => 'string',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
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

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function canBeReviewed(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function canBeShipped(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function canBeDelivered(): bool
    {
        return $this->status === self::STATUS_SHIPPED;
    }
}
