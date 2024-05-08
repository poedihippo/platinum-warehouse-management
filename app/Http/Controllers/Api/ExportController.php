<?php

namespace App\Http\Controllers\Api;

use App\Enums\ImportType;
use App\Exports\ImportSampleExport;
use Illuminate\Http\RedirectResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Http\Controllers\Controller;

class ExportController extends Controller
{
    public function sample(string $importType): RedirectResponse | BinaryFileResponse
    {
        $importType = ImportType::coerce($importType);
        if (!$importType) {
            return redirect()->back()->with('error', 'Data import not found');
        }

        return Excel::download(new ImportSampleExport($importType), $importType->description . '.csv',  \Maatwebsite\Excel\Excel::CSV);
    }
}
