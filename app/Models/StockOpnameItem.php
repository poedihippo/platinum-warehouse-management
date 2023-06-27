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

    protected static function booted()
    {
        static::updated(function ($model) {
            if ($model->isDirty('is_scanned')) {
                $parentStock = Stock::find($model->stock_id);
                if ($parentStock && $parentStock->childs->isNotEmpty()) {
                    if ($model->is_scanned) {
                        $parentStock->childs->each(fn ($stock) => self::where('stock_opname_detail_id', $model->stock_opname_detail_id)->where('stock_id', $stock->id)->update(['is_scanned' => 1]));
                    } else {
                        $parentStock->childs->each(fn ($stock) => self::where('stock_opname_detail_id', $model->stock_opname_detail_id)->where('stock_id', $stock->id)->update(['is_scanned' => 0]));
                    }
                }
            }
        });
    }

    public function stock()
    {
        return $this->belongsTo(Stock::class);
    }
}
