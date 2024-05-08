<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait Tenanted
{
    public function scopeTenanted(Builder $query)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        if ($user->hasRole('admin')) return $query;
        return $query->whereIn('warehouse_id', $user->warehouses()->pluck('warehouse_id') ?? []);
    }

    public function scopeFindTenanted(Builder $query, int|string $id, array $columns = ['*'], bool $fail = true): self
    {
        $query->tenanted()->where('id', $id);
        if ($fail) return $query->firstOrFail($columns);

        return $query->first($columns);
    }
}
