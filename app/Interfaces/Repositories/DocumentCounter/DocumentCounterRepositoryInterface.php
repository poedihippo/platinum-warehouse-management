<?php

namespace App\Interfaces\Repositories\DocumentCounter;

use App\Interfaces\Repositories\BaseRepositoryInterface;
use App\Models\DocumentCounter;
use Illuminate\Support\Collection;

interface DocumentCounterRepositoryInterface extends BaseRepositoryInterface
{
    public function forPeriod(mixed $period): Collection;

    public function lockScope(mixed $scope): DocumentCounter;

    public function updateOrCreateScope(mixed $attributes, mixed $values): DocumentCounter;
}
