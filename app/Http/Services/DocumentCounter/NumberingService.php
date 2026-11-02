<?php

namespace App\Http\Services\DocumentCounter;

use App\Enums\CompanyEnum;
use App\Enums\DocumentType;
use App\Interfaces\Repositories\DocumentCounter\DocumentCounterRepositoryInterface;
use App\Interfaces\Services\DocumentCounter\NumberingServiceInterface;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class NumberingService implements NumberingServiceInterface
{
    public function __construct(protected DocumentCounterRepositoryInterface $repository)
    {
    }

    /**
     * Whether document numbers are generated from the document counter.
     *
     * When disabled the caller supplies the number manually, so nothing is reserved here and
     * the counter table, endpoints and formatting are left unused but fully intact.
     */
    public function enabled(): bool
    {
        return (bool) config('app.enable_document_counter');
    }

    /**
     * Reserve the next document number for the given company, document type and month.
     *
     * The sequence is scoped per company + document type + period, so every scope keeps its
     * own independent counter that resets when the period changes. The counter is incremented
     * inside the caller's transaction, so a caller that inserts the document in the same
     * transaction leaves no gap when that transaction is rolled back.
     */
    public function next(CompanyEnum $company, DocumentType $type, ?CarbonInterface $date = null): string
    {
        $date = $date ?? now();
        $period = $date->format('Y-m');

        return DB::transaction(function () use ($company, $type, $period, $date) {
            $counter = $this->repository->lockScope([
                'company' => $company->value,
                'document_type' => $type->value,
                'period' => $period,
            ]);

            $sequence = $counter->sequence + 1;
            $number = $this->format($company, $type, $date, $sequence);

            $counter->update([
                'sequence' => $sequence,
            ]);

            return $number;
        });
    }

    /**
     * Render a document number using the company specific segment order.
     *
     * The sequence is left padded to at least two digits and grows beyond that when needed.
     */
    public function format(CompanyEnum $company, DocumentType $type, CarbonInterface $date, int $sequence): string
    {
        return strtr($company->invoiceNumberFormat(), [
            '{company}' => strtoupper($company->value),
            '{type}' => strtoupper($type->value),
            '{month}' => $date->format('m'),
            '{year}' => $date->format('y'),
            '{sequence}' => str_pad((string) $sequence, 2, '0', STR_PAD_LEFT),
        ]);
    }
}
