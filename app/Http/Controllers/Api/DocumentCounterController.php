<?php

namespace App\Http\Controllers\Api;

use App\Enums\CompanyEnum;
use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DocumentCounterIndexRequest;
use App\Http\Requests\Api\DocumentCounterUpdateRequest;
use App\Http\Resources\DocumentCounterResource;
use App\Models\DocumentCounter;
use App\Services\NumberingService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DocumentCounterController extends Controller
{
    /**
     * Every company and document type pair handled by NumberingService.
     *
     * Each entry holds the value of a CompanyEnum followed by the value of a DocumentType.
     */
    private const SCOPES = [
        [CompanyEnum::PAS, DocumentType::SO],
        [CompanyEnum::PAS, DocumentType::DO],
        [CompanyEnum::PA, DocumentType::SO],
        [CompanyEnum::PA, DocumentType::DO],
    ];

    public function __construct()
    {
        parent::__construct();

        abort_unless(NumberingService::enabled(), 404);

        $this->middleware('permission:document_counter_read', ['only' => ['index']]);
        $this->middleware('permission:document_counter_edit', ['only' => ['update']]);
    }

    /**
     * Expose the counters of one period, defaulting to the period currently running.
     *
     * A scope without a stored counter is synthesized with sequence 0, so the client
     * immediately sees 1 as the number of the next document. The response stays free of
     * write side effects: a counter is only persisted once somebody saves it.
     */
    public function index(DocumentCounterIndexRequest $request): AnonymousResourceCollection
    {
        $period = $request->validated('period') ?: now()->format('Y-m');
        $company = $request->validated('company');
        $documentType = $request->validated('document_type');

        $stored = DocumentCounter::query()
            ->where('period', $period)
            ->get()
            ->keyBy(fn (DocumentCounter $counter) => $this->scopeKey($counter->company->value, $counter->document_type->value));

        $counters = collect(self::SCOPES)
            ->filter(fn (array $scope) => $company === null || $scope[0] === $company)
            ->filter(fn (array $scope) => $documentType === null || $scope[1] === $documentType)
            ->map(fn (array $scope) => $stored->get($this->scopeKey($scope[0], $scope[1])) ?? new DocumentCounter([
                'company' => $scope[0],
                'document_type' => $scope[1],
                'period' => $period,
                'sequence' => 0,
            ]));

        return DocumentCounterResource::collection($counters);
    }

    /**
     * Set the number the next document of a scope will receive.
     *
     * The counter is created when it does not exist yet, which allows preparing the
     * numbering of a period before any document has been issued in it. The response
     * therefore carries 201 for a newly created counter and 200 for an updated one.
     */
    public function update(DocumentCounterUpdateRequest $request): DocumentCounterResource
    {
        $validated = $request->validated();

        $counter = DocumentCounter::query()->updateOrCreate(
            [
                'company' => $validated['company'],
                'document_type' => $validated['document_type'],
                'period' => $validated['period'],
            ],
            [
                'sequence' => $validated['next_sequence'] - 1,
            ],
        );

        return new DocumentCounterResource($counter);
    }

    /**
     * Build the unique lookup key of a counter scope.
     */
    private function scopeKey(string $company, string $documentType): string
    {
        return $company.'|'.$documentType;
    }
}
