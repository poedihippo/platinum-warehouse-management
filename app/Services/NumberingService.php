<?php

namespace App\Services;

use App\Enums\CompanyEnum;
use App\Enums\DocumentType;
use App\Models\DocumentCounter;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class NumberingService
{
    /**
     * Reserve the next document number for the given company, document type and month.
     *
     * The sequence is scoped per company + document type + period, so every scope keeps its
     * own independent counter that resets when the period changes. The counter is incremented
     * inside the caller's transaction, so a caller that inserts the document in the same
     * transaction leaves no gap when that transaction is rolled back.
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
     *
     * The row is created with an upsert instead of a locking read followed by an insert: a
     * locking read on a missing row leaves a gap lock behind, and two transactions holding
     * that gap lock deadlock as soon as both try to insert. An upsert ends up holding an
     * exclusive row lock, so concurrent callers serialise instead of deadlocking.
     *
     * The existence check reads the snapshot and therefore takes no lock, so it cannot
     * reintroduce the gap lock. It only avoids rewriting the counter on every document;
     * correctness comes from the locking read below, which always reads the latest
     * committed sequence, including one committed by a request that raced us.
     */
    private static function lockCounter(CompanyEnum $company, DocumentType $type, string $period): DocumentCounter
    {
        $scope = [
            'company' => $company->value,
            'document_type' => $type->value,
            'period' => $period,
        ];

        if (! DocumentCounter::query()->where($scope)->exists()) {
            $now = now();

            DocumentCounter::query()->upsert(
                [$scope + ['sequence' => 0, 'created_at' => $now, 'updated_at' => $now]],
                ['company', 'document_type', 'period'],
                ['period'],
            );
        }

        return DocumentCounter::query()
            ->where($scope)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
