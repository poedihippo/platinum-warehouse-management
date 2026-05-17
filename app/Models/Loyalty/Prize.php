<?php

namespace App\Models\Loyalty;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Prize catalog entry. NOT soft-deleted; hidden via is_active=false.
 */
class Prize extends Model
{
    use HasFactory, HasUlids;

    protected $guarded = [];

    protected $casts = [
        'point_cost' => 'integer',
        'stock' => 'integer',
        'is_active' => 'boolean',
    ];

    public function redemptions(): HasMany
    {
        return $this->hasMany(Redemption::class);
    }
}
