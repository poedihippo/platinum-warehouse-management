<?php

namespace App\Http\Services\ReceiveOrder;

use App\Exceptions\ApiResponseException;
use App\Http\Services\BaseService;
use App\Interfaces\Repositories\ProductUnit\ProductUnitRepositoryInterface;
use App\Interfaces\Repositories\ReceiveOrder\ReceiveOrderRepositoryInterface;
use App\Interfaces\Repositories\Supplier\SupplierRepositoryInterface;
use App\Interfaces\Repositories\Warehouse\WarehouseRepositoryInterface;
use App\Interfaces\Services\ReceiveOrder\ReceiveOrderServiceInterface;
use App\Models\ReceiveOrder;
use Illuminate\Support\Facades\DB;

class ReceiveOrderService extends BaseService implements ReceiveOrderServiceInterface
{
    public function __construct(
        protected ReceiveOrderRepositoryInterface $repository,
        protected ProductUnitRepositoryInterface $productUnitRepository,
        protected SupplierRepositoryInterface $supplierRepository,
        protected WarehouseRepositoryInterface $warehouseRepository,
    ) {
        parent::__construct($repository);
    }

    /**
     * Builds the receive order and its details from an Accurate RECIEVEITEM export.
     * Blacklisted product units are skipped; an unknown product code aborts the import.
     */
    public function createFromXml(mixed $file, mixed $receiveDatetime): ReceiveOrder
    {
        $xmlString = file_get_contents($file);
        $xmlObject = simplexml_load_string($xmlString);
        $json = json_encode($xmlObject);
        $xmlArray = json_decode($json, true);

        $invoiceNo = $xmlArray['TRANSACTIONS']['RECIEVEITEM']['INVOICENO'];
        if ($this->repository->invoiceNoExists($invoiceNo)) {
            throw new ApiResponseException('Invoice number sudah digunakan', 400);
        }

        $productUnitBlacklist = DB::table('product_unit_blacklists')->select('product_unit_id')->get()?->pluck('product_unit_id')?->all() ?? [];
        $supplier = $this->supplierRepository->query()->where('code', $xmlArray['TRANSACTIONS']['RECIEVEITEM']['VENDORID'])->firstOrFail();
        $warehouse = $this->warehouseRepository->query()->where('code', $xmlArray['TRANSACTIONS']['RECIEVEITEM']['WAREHOUSEID'])->firstOrFail();

        DB::beginTransaction();
        try {
            $receiveOrder = $this->repository->create([
                'user_id' => auth('sanctum')->id(),
                'supplier_id' => $supplier?->id ?? null,
                'warehouse_id' => $warehouse?->id ?? null,
                'receive_datetime' => date('Y-m-d H:i:s', strtotime($receiveDatetime)),
                'invoice_no' => $invoiceNo,
                'invoice_date' => date('Y-m-d', strtotime($xmlArray['TRANSACTIONS']['RECIEVEITEM']['INVOICEDATE'])),
                'invoice_amount' => $xmlArray['TRANSACTIONS']['RECIEVEITEM']['INVOICEAMOUNT'],
                'purchase_order_no' => $xmlArray['TRANSACTIONS']['RECIEVEITEM']['PURCHASEORDERNO'],
                'warehouse_string_id' => $xmlArray['TRANSACTIONS']['RECIEVEITEM']['WAREHOUSEID'],
                'vendor_id' => $xmlArray['TRANSACTIONS']['RECIEVEITEM']['VENDORID'],
                'sequence_no' => $xmlArray['TRANSACTIONS']['RECIEVEITEM']['SEQUENCENO'],
            ]);

            $itemlines = $xmlArray['TRANSACTIONS']['RECIEVEITEM']['ITEMLINE'];
            if (isset($itemlines['ITEMNO'])) {
                $itemlines = [$itemlines];
            }

            foreach ($itemlines as $item) {
                $productUnit = $this->productUnitRepository->query()->where('code', $item['ITEMNO'])->first();
                if (! $productUnit) {
                    throw new ApiResponseException('Product '.$item['ITEMNO'].' Tidak ditemukan on system. Please add first', 400);
                }

                if (! in_array($productUnit->id, $productUnitBlacklist)) {
                    $receiveOrder->details()->create([
                        'product_unit_id' => $productUnit->id,
                        'qty' => $item['QUANTITY'],
                        'item_unit' => $item['ITEMUNIT'],
                        'bruto_unit_price' => $item['BRUTOUNITPRICE'],
                    ]);
                }
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            throw new ApiResponseException($th->getMessage(), 400);
        }

        return $receiveOrder;
    }

    public function deleteIfUnverified(ReceiveOrder $receiveOrder): void
    {
        if (! $receiveOrder->details->every(fn ($detail) => $detail->is_verified === false)) {
            throw new ApiResponseException('Semua receive order harus unverified', 200);
        }

        $this->repository->delete($receiveOrder);
    }

    /**
     * Stock is created or removed by the listeners of the ReceiveOrder updated hook.
     */
    public function setDone(ReceiveOrder $receiveOrder, mixed $isDone): ReceiveOrder
    {
        if (! $receiveOrder->details?->every(fn ($detail) => $detail->is_verified === true)) {
            throw new ApiResponseException('Semua receive order harus diverifikasi', 400);
        }
        if (! $receiveOrder->details?->every(fn ($detail) => $detail->adjust_qty > 0)) {
            throw new ApiResponseException('Semua qty detail receive order harus di adjust', 400);
        }

        $receiveOrder->update([
            'is_done' => $isDone ?? 1,
            'done_at' => now(),
        ]);

        return $receiveOrder;
    }
}
