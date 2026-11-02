<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\DocumentCounter\IndexRequest;
use App\Http\Requests\Api\DocumentCounter\UpdateRequest;
use App\Http\Resources\DocumentCounter\DocumentCounterResource;
use App\Interfaces\Services\DocumentCounter\DocumentCounterServiceInterface;
use App\Interfaces\Services\DocumentCounter\NumberingServiceInterface;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DocumentCounterController extends BaseController
{
    public function __construct(
        private DocumentCounterServiceInterface $service,
        private NumberingServiceInterface $numberingService,
    ) {
        parent::__construct();

        abort_unless($this->numberingService->enabled(), 404);

        $this->middleware('permission:document_counter_read', ['only' => ['index']]);
        $this->middleware('permission:document_counter_edit', ['only' => ['update']]);
    }

    public function index(IndexRequest $request): AnonymousResourceCollection
    {
        $period = $request->validated('period') ?: now()->format('Y-m');

        $counters = $this->service->countersForPeriod(
            $period,
            $request->validated('company'),
            $request->validated('document_type'),
        );

        return DocumentCounterResource::collection($counters);
    }

    public function update(UpdateRequest $request): DocumentCounterResource
    {
        $counter = $this->service->setNextSequence($request->validated());

        return new DocumentCounterResource($counter);
    }
}
