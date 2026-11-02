<?php

namespace App\Interfaces\Repositories\StockHistory;

use App\Interfaces\Repositories\BaseRepositoryInterface;
use App\Models\StockHistory;
use Illuminate\Database\Eloquent\Model;

interface StockHistoryRepositoryInterface extends BaseRepositoryInterface
{
    public function record(Model $subject, array $attributes): StockHistory;
}
