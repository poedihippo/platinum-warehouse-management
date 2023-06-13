<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDiscount extends Model
{
    protected $guarded = [];
    protected $casts = [
        'value' => 'float'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function productBrand()
    {
        return $this->belongsTo(ProductBrand::class);
    }
}
