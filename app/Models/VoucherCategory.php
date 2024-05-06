<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Traits\CustomSoftDeletes;
use Illuminate\Database\Eloquent\Model;

class VoucherCategory extends Model
{
    use CustomSoftDeletes;

    protected $fillable = [
        'name',
        'discount_type',
        'discount_amount',
        'description',
    ];

    protected $casts = [
        'discount_type' => DiscountType::class,
        'discount_amount' => 'float',
    ];
}
