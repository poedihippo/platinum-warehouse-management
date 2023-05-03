<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $fillable = [
        'name',
        'token',
        'plain_text_token',
        'abilities',
        'expires_at',
    ];

    public function user(){
        return $this->belongsTo(User::class, 'tokenable_id');
    }
}
