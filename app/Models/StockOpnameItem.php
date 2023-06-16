<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockOpnameItem extends Model
{
    protected $guarded = [];
    protected $casts = [
        'is_scanned' => 'boolean',
        'is_new' => 'boolean',
    ];

    public function stock()
    {
        return $this->belongsTo(Stock::class);
    }
}
