<?php

namespace App\Models;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Spatie\Permission\Models\Permission as ModelsPermission;
use Spatie\Permission\PermissionRegistrar;

class Permission extends ModelsPermission
{
    public $table = 'permissions';

    protected $guarded = [];

    /**
     * laravel-permission 6 also detaches `users()` when a permission is deleted.
     * A permission created through the API gets the request guard ("sanctum"),
     * which has no user provider, so that relation cannot be built and the
     * delete would fail. Keep the permission 5 behaviour: detach the roles only;
     * model_has_permissions rows go through the foreign key cascade.
     */
    public static function bootHasRoles()
    {
        static::deleting(function ($model) {
            $registrar = app(PermissionRegistrar::class);
            $teams = $registrar->teams;
            $registrar->teams = false;
            $model->roles()->detach();
            $registrar->teams = $teams;
        });
    }

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
        $query->whereHas('roles', fn ($q) => $q->where('id', $id));
    }
}
