<?php

namespace App\Http\Services\DocumentCounter;

use App\Enums\CompanyEnum;
use App\Enums\DocumentType;
use App\Http\Services\BaseService;
use App\Interfaces\Repositories\DocumentCounter\DocumentCounterRepositoryInterface;
use App\Interfaces\Services\DocumentCounter\DocumentCounterServiceInterface;
use App\Models\DocumentCounter;
use Illuminate\Support\Collection;

class DocumentCounterService extends BaseService implements DocumentCounterServiceInterface
{
    /**
     * Every company and document type pair handled by NumberingService.
     *
     * Each entry holds a CompanyEnum case followed by a DocumentType case.
     */
    private const SCOPES = [
        [CompanyEnum::PAS, DocumentType::SO],
        [CompanyEnum::PAS, DocumentType::DO],
        [CompanyEnum::PA, DocumentType::SO],
        [CompanyEnum::PA, DocumentType::DO],
    ];

    public function __construct(protected DocumentCounterRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    /**
     * Expose the counters of one period, defaulting to the period currently running.
     *
     * A scope without a stored counter is synthesized with sequence 0, so the client
     * immediately sees 1 as the number of the next document. The response stays free of
     * write side effects: a counter is only persisted once somebody saves it.
     */
    public function countersForPeriod(string $period, ?string $company = null, ?string $documentType = null): Collection
    {
        $stored = $this->repository->forPeriod($period)
            ->keyBy(fn (DocumentCounter $counter) => $this->scopeKey($counter->company->value, $counter->document_type->value));

        return collect(self::SCOPES)
            ->filter(fn (array $scope) => $company === null || $scope[0]->value === $company)
            ->filter(fn (array $scope) => $documentType === null || $scope[1]->value === $documentType)
            ->map(fn (array $scope) => $stored->get($this->scopeKey($scope[0]->value, $scope[1]->value)) ?? new DocumentCounter([
                'company' => $scope[0],
                'document_type' => $scope[1],
                'period' => $period,
                'sequence' => 0,
            ]));
    }

    /**
     * Set the number the next document of a scope will receive.
     *
     * The counter is created when it does not exist yet, which allows preparing the
     * numbering of a period before any document has been issued in it. The response
     * therefore carries 201 for a newly created counter and 200 for an updated one.
     */
    public function setNextSequence(array $validated): DocumentCounter
    {
        return $this->repository->updateOrCreateScope(
            [
                'company' => $validated['company'],
                'document_type' => $validated['document_type'],
                'period' => $validated['period'],
            ],
            [
                'sequence' => $validated['next_sequence'] - 1,
            ],
        );
    }

    /**
     * Build the unique lookup key of a counter scope.
     */
    private function scopeKey(string $company, string $documentType): string
    {
        return $company.'|'.$documentType;
    }
}
