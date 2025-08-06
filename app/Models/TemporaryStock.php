<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemporaryStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'created_by_id',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function stock()
    {
        return $this->hasOne(Stock::class, 'id', 'id');
    }
}
