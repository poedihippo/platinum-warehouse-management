<?php

namespace App\Http\Services\StockOpname;

use App\Exceptions\ApiResponseException;
use App\Http\Services\BaseService;
use App\Interfaces\Repositories\StockOpname\StockOpnameRepositoryInterface;
use App\Interfaces\Services\StockOpname\StockOpnameServiceInterface;
use App\Models\StockOpname;
use App\Models\StockOpnameDetail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockOpnameService extends BaseService implements StockOpnameServiceInterface
{
    public function __construct(protected StockOpnameRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    /**
     * Unscanned stocks are soft deleted (done) or restored (pending) by the
     * StockOpname saved hook.
     */
    public function setDone(StockOpname $stockOpname, mixed $isDone): StockOpname
    {
        if (! $stockOpname->details->every(fn ($detail) => $detail->is_done === true)) {
            throw new ApiResponseException('Semua data stock opname harus diset selesai', 400);
        }

        $stockOpname->update([
            'is_done' => $isDone,
            'done_at' => now(),
        ]);

        return $stockOpname;
    }

    public function setAllDetailsDone(StockOpname $stockOpname, mixed $isDone): void
    {
        $stockOpname->details->each->update([
            'is_done' => $isDone,
            'done_at' => now(),
        ]);
    }

    public function items(mixed $stockOpnameDetailId): Collection
    {
        return DB::table('stock_opname_items')->select('stock_id', 'is_scanned', 'created_at', 'updated_at')->where('stock_opname_detail_id', $stockOpnameDetailId)->get();
    }

    public function deleteItem(StockOpnameDetail $stockOpnameDetail, mixed $stockId): void
    {
        DB::beginTransaction();
        try {
            $stockOpnameDetail->stockOpnameItems()->where('stock_id', $stockId)->delete();
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            throw new ApiResponseException($th->getMessage(), 500);
        }
    }
}
