<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'company_name'
    ];

    protected static function booted()
    {
        static::created(function ($model) {
            ProductUnit::get(['id'])->each(fn ($productUnit) => $productUnit->stockProductUnits()->create([
                'warehouse_id' => $model->id
            ]));
        });
    }

    public function scopeTenanted(Builder $query)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        if ($user->hasRole('admin')) return $query;
        return $query->whereIn('id', $user->warehouses()->pluck('warehouse_id') ?? []);
    }

    public function scopeFindTenanted(Builder $query, int|string $id, array $columns = ['*'], bool $fail = true): self
    {
        $query->tenanted()->where('id', $id);
        if ($fail) return $query->firstOrFail($columns);

        return $query->first($columns);
    }
}
