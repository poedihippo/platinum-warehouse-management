<?php

namespace App\Models;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Spatie\Permission\Models\Permission as ModelsPermission;

class Permission extends ModelsPermission
{
    public $table = 'permissions';
    protected $guarded = [];

    public function childs()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function scopeWhereParent(Builder $query)
    {
        $query->whereNull('parent_id');
    }

    public function scopeWhereRoleId(Builder $query, $id)
    {
        $query->whereHas('roles', fn($q) => $q->where('id', $id));
    }
}
