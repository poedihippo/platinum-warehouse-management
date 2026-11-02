<?php

namespace App\Http\Repositories\DocumentCounter;

use App\Http\Repositories\BaseRepository;
use App\Interfaces\Repositories\DocumentCounter\DocumentCounterRepositoryInterface;
use App\Models\DocumentCounter;
use Illuminate\Support\Collection;

class DocumentCounterRepository extends BaseRepository implements DocumentCounterRepositoryInterface
{
    public function __construct(DocumentCounter $model)
    {
        parent::__construct($model);
    }

    public function forPeriod(mixed $period): Collection
    {
        return $this->query()
            ->where('period', $period)
            ->get();
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
    public function lockScope(mixed $scope): DocumentCounter
    {
        if (! $this->query()->where($scope)->exists()) {
            $now = now();

            $this->query()->upsert(
                [$scope + ['sequence' => 0, 'created_at' => $now, 'updated_at' => $now]],
                ['company', 'document_type', 'period'],
                ['period'],
            );
        }

        return $this->query()
            ->where($scope)
            ->lockForUpdate()
            ->firstOrFail();
    }

    public function updateOrCreateScope(mixed $attributes, mixed $values): DocumentCounter
    {
        return $this->query()->updateOrCreate($attributes, $values);
    }
}
