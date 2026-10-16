<?php

namespace App\Services;

use App\Enums\CompanyEnum;
use App\Enums\DocumentType;
use App\Models\DocumentCounter;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class NumberingService
{
    /**
     * Reserve the next document number for the given company, document type and month.
     *
     * The sequence is scoped per company + document type + period, so every scope keeps its
     * own independent counter that resets when the period changes. The counter is incremented
     * inside the caller's transaction, meaning a rolled back document leaves no gap.
     */
    public static function next(CompanyEnum $company, DocumentType $type, ?CarbonInterface $date = null): string
    {
        $date = $date ?? now();
        $period = $date->format('Y-m');

        return DB::transaction(function () use ($company, $type, $period, $date) {
            $counter = self::lockCounter($company, $type, $period);

            $sequence = $counter->sequence + 1;
            $number = self::format($company, $type, $date, $sequence);

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
    public static function format(CompanyEnum $company, DocumentType $type, CarbonInterface $date, int $sequence): string
    {
        return strtr($company->invoiceNumberFormat(), [
            '{company}' => strtoupper($company->value),
            '{type}' => strtoupper($type->value),
            '{month}' => $date->format('m'),
            '{year}' => $date->format('y'),
            '{sequence}' => str_pad((string) $sequence, 2, '0', STR_PAD_LEFT),
        ]);
    }

    /**
     * Lock the counter row of the given scope, creating it on first use of the period.
     */
    private static function lockCounter(CompanyEnum $company, DocumentType $type, string $period): DocumentCounter
    {
        $scope = [
            'company' => $company->value,
            'document_type' => $type->value,
            'period' => $period,
        ];

        $counter = DocumentCounter::query()
            ->where('company', $company->value)
            ->where('document_type', $type->value)
            ->where('period', $period)
            ->lockForUpdate()
            ->first();

        if ($counter) {
            return $counter;
        }

        try {
            DocumentCounter::create([
                'company' => $company->value,
                'document_type' => $type->value,
                'period' => $period,
                'sequence' => 0,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Another request created the counter of this period first, lock it below instead.
        }

        return DocumentCounter::query()
            ->where('company', $company->value)
            ->where('document_type', $type->value)
            ->where('period', $period)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
