<?php

namespace App\Http\Services\Stock;

use App\Exceptions\ApiResponseException;
use App\Exports\StockExport;
use App\Http\Services\BaseService;
use App\Imports\StockImport;
use App\Interfaces\Repositories\Stock\StockRepositoryInterface;
use App\Interfaces\Repositories\StockProductUnit\StockProductUnitRepositoryInterface;
use App\Interfaces\Services\Stock\StockServiceInterface;
use App\Models\SalesOrderItem;
use App\Models\Stock;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StockService extends BaseService implements StockServiceInterface
{
    public function __construct(
        protected StockRepositoryInterface $repository,
        protected StockProductUnitRepositoryInterface $stockProductUnitRepository,
    ) {
        parent::__construct($repository);
    }

    public function paginateStockProductUnits(int $perPage): LengthAwarePaginator
    {
        return $this->stockProductUnitRepository->paginateStockIndex($perPage);
    }

    public function paginateDetails(mixed $filter, int $perPage): LengthAwarePaginator
    {
        return $this->repository->paginateDetails($filter, $perPage);
    }

    public function findForShow(mixed $id, mixed $include): Stock
    {
        return $this->repository->findForShow($id, $include);
    }

    public function updateWithChildren(Stock $stock, mixed $data): void
    {
        $this->repository->updateWithChildren($stock, $data);
    }

    public function deleteSingle(Stock $stock): void
    {
        if ($stock->childs()->count() > 0) {
            throw new ApiResponseException('Tidak dapat menghapus stock parent', 400);
        }
        if ($stock->salesOrderItems()->whereNotReturned()->count() > 0) {
            throw new ApiResponseException('Tidak dapat menghapus stock. Stock sudah masuk di Sales Order', 400);
        }

        $stock->delete();
    }

    public function bulkDelete(mixed $ids): void
    {
        if (SalesOrderItem::whereIn('stock_id', $ids)->whereNotReturned()->limit(1)->exists()) {
            throw new ApiResponseException('Tidak dapat menghapus stock. Terdapat stock sudah masuk di Delivery Order', 400);
        }

        $this->repository->deleteWithChildren($ids);
    }

    public function printAll(mixed $filter, mixed $printWithParent): Collection
    {
        return $this->repository->printAll($filter, $printWithParent);
    }

    /**
     * Increments scanned_count of the given stocks (and the children of a group),
     * or of every stock of the scope when is_print_all is sent without ids.
     */
    public function record(mixed $stockIds, mixed $stockProductUnitId, mixed $receiveOrderDetailId, mixed $isPrintAll): string
    {
        $stockIds = is_array($stockIds) && count($stockIds) > 0 ? $stockIds : [];
        $columnName = '';
        $value = '';
        if ($stockProductUnitId) {
            $columnName = 'stock_product_unit_id';
            $value = $stockProductUnitId;
        } elseif ($receiveOrderDetailId) {
            $columnName = 'receive_order_detail_id';
            $value = $receiveOrderDetailId;
        }

        if ($columnName != '' && $value != '') {
            DB::beginTransaction();
            try {
                $time = now();
                if (count($stockIds) > 0) {
                    foreach ($stockIds as $id) {
                        $stock = Stock::tenanted()->where($columnName, $value)->where('id', $id)->first();
                        if ($stock) {
                            if ($stock->childs->count() > 0) {
                                $stock->increment('scanned_count', 1, ['scanned_datetime' => $time]);
                                $stock->childs->each->increment('scanned_count', 1, ['scanned_datetime' => $time]);
                            } else {
                                $stock->increment('scanned_count', 1, ['scanned_datetime' => $time]);
                            }
                        }
                    }
                } elseif ($isPrintAll) {
                    DB::table('stocks')->where($columnName, $value)->increment('scanned_count', 1, ['scanned_datetime' => $time]);
                }
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();

                throw new ApiResponseException($e->getMessage(), 400);
            }

            return 'Data berhasil diupdate';
        }

        return 'Tidak ada data yang diupdate';
    }

    public function import(mixed $file, mixed $warehouseId): void
    {
        Excel::import(new StockImport($warehouseId ?? 1), $file);
    }

    public function setTempel(mixed $ids, mixed $isTempel): void
    {
        DB::beginTransaction();
        try {
            $this->repository->updateWithChildrenIn($ids, ['is_tempel' => $isTempel]);
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function export(): BinaryFileResponse
    {
        return Excel::download(new StockExport, 'stock-'.date('Y-m-d H:i').'.xlsx');
    }

    public function setToPrinted(mixed $ids): void
    {
        $this->repository->updateIn($ids, [
            'printed_at' => now(),
            'in_printing_queue' => 0,
        ]);
    }

    public function setToPrintingQueue(mixed $ids, mixed $printerId, mixed $expiredDate): void
    {
        $data = [
            'printer_id' => $printerId,
            'printed_at' => null,
            'in_printing_queue' => 1,
        ];

        if ($expiredDate) {
            $data['expired_date'] = $expiredDate;
        }

        $this->repository->updateIn($ids, $data);
    }

    public function findPrintedForPreview(mixed $ids): Collection
    {
        return $this->repository->findPrintedForPreview($ids);
    }

    public function setIsStock(mixed $ids, mixed $isAdd): void
    {
        $this->repository->updateIn($ids, [
            'is_stock' => $isAdd,
        ]);
    }

    public function idsAsText(mixed $ids): string
    {
        $content = implode("\n", $ids);
        if (strlen($content) > 0 && substr($content, -1) !== "\n") {
            $content .= "\n";
        }

        return $content;
    }

    public function findForPublicVerification(mixed $ulid): ?Stock
    {
        return $this->repository->findForPublicVerification($ulid);
    }
}
