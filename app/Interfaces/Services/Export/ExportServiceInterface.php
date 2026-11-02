<?php

namespace App\Interfaces\Services\Export;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

interface ExportServiceInterface
{
    public function sample(string $importType): ?BinaryFileResponse;
}
