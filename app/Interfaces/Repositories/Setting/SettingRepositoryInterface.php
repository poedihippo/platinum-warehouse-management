<?php

namespace App\Interfaces\Repositories\Setting;

use App\Interfaces\Repositories\BaseRepositoryInterface;
use Illuminate\Support\Collection;

interface SettingRepositoryInterface extends BaseRepositoryInterface
{
    public function all(): Collection;

    public function valueOf(string $key): mixed;
}
