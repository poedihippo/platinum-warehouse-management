<?php

namespace App\Http\Repositories\Setting;

use App\Http\Repositories\BaseRepository;
use App\Interfaces\Repositories\Setting\SettingRepositoryInterface;
use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SettingRepository extends BaseRepository implements SettingRepositoryInterface
{
    public function __construct(Setting $model)
    {
        parent::__construct($model);
    }

    public function all(): Collection
    {
        return $this->model::all();
    }

    public function valueOf(mixed $key): mixed
    {
        return DB::table('settings')->where('key', $key)->first(['value'])->value ?? null;
    }
}
