<?php

namespace App\Http\Services\Stock;

use App\Exceptions\ApiResponseException;
use App\Interfaces\Repositories\ReceiveOrder\ReceiveOrderDetailRepositoryInterface;
use App\Interfaces\Repositories\Stock\StockRepositoryInterface;
use App\Interfaces\Repositories\StockProductUnit\StockProductUnitRepositoryInterface;
use App\Interfaces\Services\Stock\StockGroupingServiceInterface;
use App\Models\Stock;
use App\Models\StockProductUnit;
use Exception;
use Illuminate\Support\Facades\DB;

class StockGroupingService implements StockGroupingServiceInterface
{
    public function __construct(
        protected StockRepositoryInterface $repository,
        protected StockProductUnitRepositoryInterface $stockProductUnitRepository,
        protected ReceiveOrderDetailRepositoryInterface $receiveOrderDetailRepository,
    ) {
    }

    /**
     * Groups the scanned stocks under a new parent stock named "{code} GR-{mmyy}{NNN}"
     * unless a name is given.
     */
    public function groupByScan(mixed $input): void
    {
        $stockProductUnit = $this->stockProductUnitRepository->findTenanted($input['stock_product_unit_id'] ?? null);
        $totalStock = $stockProductUnit->stocks()->whereNull('parent_id')->doesntHave('childs')->count() ?? 0;

        if ($totalStock == 0 || $totalStock < count($input['ids'] ?? null)) {
            throw new ApiResponseException('Stock tidak mencukupi', 400);
        }

        $productUnit = $stockProductUnit->productUnit;

        $description = $input['name'] ?? null;
        if (! $description) {
            $lastOrderNumberGroup = $this->repository->lastGroupSequence($stockProductUnit);

            $formatMMYY = date('my');
            $description = sprintf(StockRepositoryInterface::FORMAT_GROUPING, $productUnit->code, $formatMMYY, sprintf('%03d', (int) $lastOrderNumberGroup + 1));
        }

        DB::transaction(function () use ($input, $stockProductUnit, $description) {
            $expiredDate = ($input['expired_date'] ?? null) ? date('Y-m-d', strtotime($input['expired_date'])) : null;
            $groupStock = $this->repository->create([
                'batch_number' => $input['batch_number'] ?? null,
                'batch_number_jp' => $input['batch_number_jp'] ?? null,
                'stock_product_unit_id' => $stockProductUnit->id,
                'description' => $description,
                'expired_date' => $expiredDate,
            ]);

            $data = [
                'parent_id' => $groupStock->id,
                'batch_number' => $groupStock->batch_number,
                'batch_number_jp' => $groupStock->batch_number_jp,
                'is_stock' => 1,
            ];

            if ($input['expired_date'] ?? null) {
                $data['expired_date'] = $expiredDate;
            }

            Stock::whereIn('id', $input['ids'] ?? null)->where('stock_product_unit_id', $stockProductUnit->id)->whereNull('parent_id')->update($data);
        });
    }

    /**
     * Creates total_group groups of qty stocks each, either from the stock page
     * (stock_product_unit_id) or from the receive order page (receive_order_detail_id).
     *
     * Known issue kept as is: the receive order branch never sets the last group
     * sequence, so it fails before writing anything.
     */
    public function group(mixed $input): void
    {
        $totalQtyGrouping = ($input['total_group'] ?? null) * ($input['qty'] ?? null);
        $receiveOrderDetailId = $input['receive_order_detail_id'] ?? null;

        if ($input['stock_product_unit_id'] ?? null) {
            /** @var StockProductUnit $stockProductUnit */
            $stockProductUnit = $this->stockProductUnitRepository->findTenanted($input['stock_product_unit_id']);
            $totalStock = $stockProductUnit->stocks()->whereNull('parent_id')->doesntHave('childs')->count() ?? 0;

            if ($totalStock == 0) {
                throw new ApiResponseException('Stock tidak mencukupi', 400);
            }

            if ($totalQtyGrouping == 0 || ($totalQtyGrouping > $totalStock)) {
                throw new ApiResponseException('Jumlah total grouping stok melebihi total stok', 400);
            }

            $productUnit = $stockProductUnit->productUnit;

            $lastOrderNumberGroup = $this->repository->lastGroupSequence($stockProductUnit);
        } elseif ($input['receive_order_detail_id'] ?? null) {
            $receiveOrderDetail = $this->receiveOrderDetailRepository->findByIdOrFail($input['receive_order_detail_id']);
            $totalStock = $receiveOrderDetail->stocks()->whereNull('parent_id')->doesntHave('childs')->count() ?? 0;

            if ($totalStock == 0) {
                throw new ApiResponseException('Stock tidak mencukupi', 400);
            }

            if ($totalQtyGrouping == 0 || ($totalQtyGrouping > $totalStock)) {
                throw new ApiResponseException('Jumlah total grouping stok melebihi total stok', 400);
            }

            $productUnit = $receiveOrderDetail->productUnit;
            $stockProductUnit = StockProductUnit::tenanted()->where('warehouse_id', $receiveOrderDetail->receiveOrder->warehouse_id)
                ->where('product_unit_id', $receiveOrderDetail->product_unit_id)
                ->first();
        }

        $formatMMYY = date('my');
        for ($i = 0; $i < ($input['total_group'] ?? null); $i++) {
            $description = sprintf(StockRepositoryInterface::FORMAT_GROUPING, $productUnit->code, $formatMMYY, sprintf('%03d', (int) $lastOrderNumberGroup + 1));
            $lastOrderNumberGroup++;
            $groupStock = $this->repository->create([
                'batch_number' => $input['batch_number'] ?? null,
                'batch_number_jp' => $input['batch_number_jp'] ?? null,
                'stock_product_unit_id' => $stockProductUnit->id,
                'receive_order_detail_id' => $receiveOrderDetailId,
                'description' => $description,
            ]);

            if ($input['stock_product_unit_id'] ?? null) {
                $stocks = $stockProductUnit->stocks()->whereNull('parent_id')->where('id', '<>', $groupStock->id)->doesntHave('childs');
            } else {
                $stocks = $receiveOrderDetail->stocks()->whereNull('parent_id')->where('id', '<>', $groupStock->id)->doesntHave('childs');
            }

            if (is_null($groupStock->receive_order_detail_id)) {
                $stocks->orderBy('receive_order_detail_id');
            } else {
                $stocks->where('receive_order_detail_id', $groupStock->receive_order_detail_id);
            }

            $data = [
                'parent_id' => $groupStock->id,
                'batch_number' => $groupStock->batch_number,
                'batch_number_jp' => $groupStock->batch_number_jp,
                'is_stock' => 1,
            ];

            if ($input['expired_date'] ?? null) {
                $data['expired_date'] = $input['expired_date'];
            }

            $stocks->limit($input['qty'] ?? null)->get()?->each->update($data);
        }
    }

    public function ungroup(mixed $id): void
    {
        $stock = $this->repository->findTenanted($id);
        if ($stock->childs->isEmpty()) {
            throw new ApiResponseException('Stock bukan grouping / tidak memiliki childs', 400);
        }

        DB::beginTransaction();
        try {
            Stock::where('parent_id', $stock->id)->update(['parent_id' => null]);
            $stock->forceDelete();
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            throw new ApiResponseException($e->getMessage(), 400);
        }
    }
}
