<?php

namespace App\Interfaces\Services\SalesOrder;

use App\Interfaces\Services\BaseServiceInterface;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use App\Models\SalesOrderItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface SalesOrderServiceInterface extends BaseServiceInterface
{
    public function validateTotalPrice(int $totalPrice, ?int $shipmentFee, array $items): bool;

    public function verifyStockItem(SalesOrderDetail $salesOrderDetail, string|int $stockId): SalesOrderItem;

    public function releaseScannedStock(SalesOrder $salesOrder): void;

    public function storeOrder(array $validated, bool $isPreview): SalesOrder;

    public function createOrder(SalesOrder $salesOrder, bool $isPerview = false): SalesOrder;

    public function updateOrder(SalesOrder $salesOrder, bool $isPerview = false): SalesOrder;

    public function convertOrderToSO(SalesOrder $salesOrder, bool $isPerview = false): SalesOrder;

    public function index(int $perPage, ?callable $query = null);

    public function show($id, ?callable $query = null);

    public function print(int|string $id, string $type = 'print', ?callable $query = null, string $mode = 'download');

    public function exportXml($id, ?callable $query = null);

    public function getWhatsappUrl(SalesOrder $salesOrder, ?string $idHash = null);

    public function mergeMarketplaceOrders(array $data): SalesOrder;

    public function deleteMergeResult(SalesOrder $salesOrder): void;

    public function findScanableMarketplaceOrder(int $id): SalesOrder;

    public function findInvoice(int|string $id): SalesOrder;

    public function paginateItems(SalesOrderDetail $salesOrderDetail, int $perPage): LengthAwarePaginator;

    public function ensureEditable(SalesOrder $salesOrder): void;

    public function destroySalesOrder(SalesOrder $salesOrder): void;

    public function details(int $salesOrderId): Collection;

    public function findDetail(int $salesOrderId, mixed $salesOrderDetailId): SalesOrderDetail;

    public function scannedStockIds(SalesOrderDetail $salesOrderDetail): Collection;

    public function scanItem(SalesOrderDetail $salesOrderDetail, mixed $stockId): SalesOrderItem;

    public function unscanItem(SalesOrderDetail $salesOrderDetail, mixed $stockId): void;

    public function paginateProductUnitsNew(mixed $warehouseId, int $perPage): LengthAwarePaginator;

    public function paginateProductUnits(int $perPage): LengthAwarePaginator;
}
