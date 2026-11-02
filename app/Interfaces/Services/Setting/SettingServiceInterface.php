<?php

namespace App\Interfaces\Services\Setting;

use App\Interfaces\Services\BaseServiceInterface;
use Illuminate\Support\Collection;

interface SettingServiceInterface extends BaseServiceInterface
{
    public function all(): Collection;

    public function bankTransferInfo(): string;
}
