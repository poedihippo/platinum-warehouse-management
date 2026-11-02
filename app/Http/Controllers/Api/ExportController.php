<?php

namespace App\Http\Controllers\Api;

use App\Interfaces\Services\Export\ExportServiceInterface;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends BaseController
{
    public function __construct(private ExportServiceInterface $service)
    {
        parent::__construct();
    }

    public function sample(string $importType): RedirectResponse|BinaryFileResponse
    {
        $download = $this->service->sample($importType);
        if (! $download) {
            return redirect()->back()->with('error', 'Data import not found');
        }

        return $download;
    }
}
