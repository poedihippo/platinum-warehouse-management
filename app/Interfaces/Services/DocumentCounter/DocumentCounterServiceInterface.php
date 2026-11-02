<?php

namespace App\Interfaces\Services\DocumentCounter;

use App\Interfaces\Services\BaseServiceInterface;
use App\Models\DocumentCounter;
use Illuminate\Support\Collection;

interface DocumentCounterServiceInterface extends BaseServiceInterface
{
    public function countersForPeriod(mixed $period, mixed $company = null, mixed $documentType = null): Collection;

    public function setNextSequence(mixed $validated): DocumentCounter;
}
