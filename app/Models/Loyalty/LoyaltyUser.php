<?php

namespace App\Models\Loyalty;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Loyalty customer account.
 *
 * Separate from App\Models\User (warehouse staff). Uses a ULID primary
 * key and the dedicated `loyalty` Sanctum guard. NOT soft-deleted —
 * account closure is a hard delete (spec §5.1).
 */
class LoyaltyUser extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUlids, Notifiable;

    protected $fillable = [
        'email',
        'name',
        'password',
        'phone',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Hash the password on assignment, mirroring App\Models\User.
     */
    protected function password(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => empty($value) ? null : bcrypt($value),
        );
    }

    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(Redemption::class);
    }

    public function pointsTransactions(): HasMany
    {
        return $this->hasMany(PointsTransaction::class);
    }

    public function hasVerifiedEmail(): bool
    {
        return !is_null($this->email_verified_at);
    }
}
