<?php

namespace App\Interfaces\Services\DocumentCounter;

use App\Interfaces\Services\BaseServiceInterface;
use App\Models\DocumentCounter;
use Illuminate\Support\Collection;

interface DocumentCounterServiceInterface extends BaseServiceInterface
{
    public function countersForPeriod(string $period, ?string $company = null, ?string $documentType = null): Collection;

    public function setNextSequence(array $validated): DocumentCounter;
}
