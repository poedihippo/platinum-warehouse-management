<?php

namespace App\Interfaces\Repositories\DocumentCounter;

use App\Interfaces\Repositories\BaseRepositoryInterface;
use App\Models\DocumentCounter;
use Illuminate\Support\Collection;

interface DocumentCounterRepositoryInterface extends BaseRepositoryInterface
{
    public function forPeriod(string $period): Collection;

    public function lockScope(array $scope): DocumentCounter;

    public function updateOrCreateScope(array $attributes, array $values): DocumentCounter;
}
