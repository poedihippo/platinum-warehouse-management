<?php

namespace App\Http\Services\Export;

use App\Enums\ImportType;
use App\Exports\ImportSampleExport;
use App\Interfaces\Services\Export\ExportServiceInterface;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportService implements ExportServiceInterface
{
    public function sample(mixed $importType): ?BinaryFileResponse
    {
        $importType = ImportType::coerce($importType);
        if (! $importType) {
            return null;
        }

        return Excel::download(new ImportSampleExport($importType), $importType->getDescription().'.csv', ExcelWriter::CSV);
    }
}
