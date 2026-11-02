<?php

namespace App\Interfaces\Services\DeliveryOrder;

use App\Interfaces\Services\BaseServiceInterface;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderDetail;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

interface DeliveryOrderServiceInterface extends BaseServiceInterface
{
    public const VERIFIED_STOCK_MESSAGE = 'Terdapat stock yang sudah di-scan pada Delivery Order. Reset verifikasi terlebih dahulu sebelum mengedit Sales Order.';

    public function paginateIndex(int $perPage): LengthAwarePaginator;

    public function findForShow(mixed $id): DeliveryOrder;

    public function findBySalesOrderDetail(mixed $salesOrderDetailId): DeliveryOrderDetail;

    public function createDeliveryOrder(mixed $data): DeliveryOrder;

    public function destroyDeliveryOrder(DeliveryOrder $deliveryOrder): void;

    public function print(mixed $id): mixed;

    public function exportXml(mixed $id): mixed;

    public function verify(mixed $id, DeliveryOrderDetail $deliveryOrderDetail, mixed $stockId): SalesOrderItem;

    public function setDone(mixed $id, mixed $isDone): DeliveryOrder;

    public function returnStocks(mixed $id, mixed $ids, mixed $isDelete): ?BadRequestHttpException;

    public function createForSalesOrder(SalesOrder $salesOrder, bool $force = false, ?string $invoiceNo = null): ?DeliveryOrder;

    public function assertEditable(SalesOrder $salesOrder): ?DeliveryOrder;

    public function lockedSalesOrderDetailIds(SalesOrder $salesOrder): Collection;

    public function normalizeLockedItems(SalesOrder $salesOrder): void;

    public function attachDetails(DeliveryOrder $deliveryOrder, iterable $details): array;

    public function attachSalesOrderDetails(DeliveryOrder $deliveryOrder, Collection $details): void;

    public function syncHeader(DeliveryOrder $deliveryOrder, SalesOrder $salesOrder): void;

    public function deleteDeliveryOrder(DeliveryOrder $deliveryOrder): void;
}
