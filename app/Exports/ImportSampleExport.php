<?php

namespace App\Exports;

use App\Enums\ImportType;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;

class ImportSampleExport implements FromCollection
{
    public function __construct(protected ImportType $importType) {}

    /**
     * @return Collection
     */
    public function collection(): Enumerable
    {
        $exporter = $this->importType->getExporter();

        return $exporter::getSample();
    }
}
