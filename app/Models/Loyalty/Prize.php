<?php

namespace App\Models\Loyalty;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Prize catalog entry. NOT soft-deleted; hidden via is_active=false.
 */
class Prize extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'name',
        'description',
        'points_cost',
        'stock',
        'photo_path',
        'is_active',
    ];

    protected $casts = [
        'points_cost' => 'integer',
        'stock' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Public S3 URL for the prize photo, or null when none is uploaded.
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->photo_path
            ? Storage::disk('s3')->url($this->photo_path)
            : null);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(Redemption::class);
    }
}
