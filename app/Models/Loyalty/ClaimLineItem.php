<?php

namespace App\Models\Loyalty;

use App\Models\ProductUnit;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Platinum product the admin identified inside a claim.
 *
 * points_awarded is captured at approval time (quantity ×
 * product_units.points_per_unit at that moment) so later changes to
 * points_per_unit never retroactively alter historical claims.
 */
class ClaimLineItem extends Model
{
    use HasFactory, HasUlids;

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'integer',
        'points_awarded' => 'integer',
    ];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }

    /**
     * The existing warehouse ProductUnit (bigint PK). points_per_unit
     * is read directly off this related model at approval time.
     */
    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class);
    }
}
