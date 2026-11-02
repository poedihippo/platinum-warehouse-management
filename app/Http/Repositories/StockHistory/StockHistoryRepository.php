<?php

namespace App\Http\Repositories\StockHistory;

use App\Http\Repositories\BaseRepository;
use App\Interfaces\Repositories\StockHistory\StockHistoryRepositoryInterface;
use App\Models\StockHistory;
use Illuminate\Database\Eloquent\Model;

class StockHistoryRepository extends BaseRepository implements StockHistoryRepositoryInterface
{
    public function __construct(StockHistory $model)
    {
        parent::__construct($model);
    }

    public function record(Model $subject, array $attributes): StockHistory
    {
        return $subject->histories()->create($attributes);
    }
}
