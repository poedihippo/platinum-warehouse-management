<?php

namespace App\Interfaces\Services\DocumentCounter;

use App\Enums\CompanyEnum;
use App\Enums\DocumentType;
use Carbon\CarbonInterface;

interface NumberingServiceInterface
{
    public function enabled(): bool;

    public function next(CompanyEnum $company, DocumentType $type, ?CarbonInterface $date = null): string;

    public function format(CompanyEnum $company, DocumentType $type, CarbonInterface $date, int $sequence): string;
}
