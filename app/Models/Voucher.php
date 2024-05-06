<?php

namespace App\Models;

use App\Traits\CustomSoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    use CustomSoftDeletes;

    protected $fillable = [
        'voucher_category_id',
        'code',
        'description',
    ];

    public function voucherCategory()
    {
        return $this->belongsTo(VoucherCategory::class);
    }
}
