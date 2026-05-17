<?php

namespace App\Models\Loyalty;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A product photo attached to a claim. created_at only (no updated_at).
 */
class ClaimPhoto extends Model
{
    use HasFactory, HasUlids;

    // Table has no updated_at column; let Eloquent manage created_at only.
    const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'position' => 'integer',
    ];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }
}
