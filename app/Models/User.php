<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Enums\UserType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'type',
        'phone',
        'address',
        'provider_id',
        'provider_name',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'type' => UserType::class
    ];

    protected static function booted()
    {
        static::created(function ($model) {
            // if user type is reseller, create user discounts
            if ($model->type->is(UserType::Reseller)) ProductBrand::select('id')->pluck('id')?->each(fn ($id) => $model->userDiscounts()->create(['product_brand_id' => $id]));
        });
    }

    /**
     * Create a new personal access token for the user.
     *
     * @param  string  $name
     * @param  array  $abilities
     * @param  \DateTimeInterface|null  $expiresAt
     * @return \Laravel\Sanctum\NewAccessToken
     */
    public function createToken(string $name, array $abilities = ['*'], \DateTimeInterface $expiresAt = null)
    {
        $token = $this->tokens()->create([
            'name' => $name,
            'token' => hash('sha256', $plainTextToken = Str::random(40)),
            'plain_text_token' => $plainTextToken,
            'abilities' => $abilities,
            'expires_at' => $expiresAt,
        ]);

        return new \Laravel\Sanctum\NewAccessToken($token, $token->getKey() . '|' . $plainTextToken);
    }

    protected function password(): Attribute
    {
        return Attribute::make(
            set: fn (string|null $value) => is_null($value) || $value == '' ? null : bcrypt($value),
        );
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function socialAccounts()
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function userDiscounts()
    {
        return $this->hasMany(UserDiscount::class);
    }
}
