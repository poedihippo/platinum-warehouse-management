<?php

namespace App\Http\Services\DeliveryOrder;

use App\Interfaces\Repositories\DeliveryOrder\DeliveryOrderRepositoryInterface;
use App\Interfaces\Repositories\SalesOrder\SalesOrderRepositoryInterface;
use App\Interfaces\Services\DeliveryOrder\DeliveryOrderDetailServiceInterface;
use App\Models\DeliveryOrderDetail;
use App\Models\SalesOrderItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class DeliveryOrderDetailService implements DeliveryOrderDetailServiceInterface
{
    public function __construct(
        protected DeliveryOrderRepositoryInterface $deliveryOrderRepository,
        protected SalesOrderRepositoryInterface $salesOrderRepository,
    ) {
    }

    public function paginate(mixed $deliveryOrderId, int $perPage): LengthAwarePaginator
    {
        $deliveryOrder = $this->deliveryOrderRepository->findTenanted($deliveryOrderId, ['id']);

        return QueryBuilder::for(
            DeliveryOrderDetail::select('id', 'delivery_order_id', 'sales_order_detail_id', 'qty')
                ->withCount(['salesOrderItems as total_verified_stock' => function ($query) {
                    $query->where('is_parent', false)->where('is_returned', false);
                }])
                ->with([
                    'deliveryOrder' => fn ($q) => $q->withCount('mergedSalesOrders')
                        ->with([
                            'reseller',
                            'mergedSalesOrders' => fn ($q) => $q->select('id', 'invoice_no', 'merged_into_sales_order_id'),
                        ]),
                    'salesOrderDetail' => fn ($q) => $q->select('id', 'sales_order_id', 'product_unit_id', 'fulfilled_qty', 'total_price')
                        ->with([
                            'salesOrder' => fn ($q) => $q->select('id', 'invoice_no', 'reseller_id')
                                ->with('reseller', fn ($q) => $q->select('id', 'name', 'phone', 'address')),
                            'productUnit' => fn ($q) => $q->withTrashed()
                                ->select('id', 'name', 'product_id', 'uom_id')
                                ->with([
                                    'uom' => fn ($q) => $q->select('id', 'name'),
                                    'product' => fn ($q) => $q->select('id', 'product_category_id', 'product_brand_id')->with([
                                        'productCategory' => fn ($q) => $q->select('id', 'name'),
                                        'productBrand' => fn ($q) => $q->select('id', 'name'),
                                    ]),
                                ]),
                        ]),
                ])->where('delivery_order_id', $deliveryOrder->id)
        )
            ->allowedFilters(
                AllowedFilter::exact('delivery_order_id'),
                AllowedFilter::exact('sales_order_detail_id'),
            )
            ->allowedSorts('id', 'delivery_order_id', 'sales_order_detail_id', 'created_at')
            ->paginate($perPage);
    }

    public function find(mixed $deliveryOrderId, mixed $deliveryOrderDetailId): DeliveryOrderDetail
    {
        $deliveryOrder = $this->deliveryOrderRepository->findTenanted($deliveryOrderId);
        $deliveryOrderDetail = $deliveryOrder->details()->where('id', $deliveryOrderDetailId)->firstOrFail();

        return $this->loadForResource($deliveryOrderDetail);
    }

    public function paginateItems(mixed $deliveryOrderDetailId, int $perPage): LengthAwarePaginator
    {
        return QueryBuilder::for(
            SalesOrderItem::query()
                ->where('delivery_order_detail_id', $deliveryOrderDetailId)
                ->select(SalesOrderItem::SELECT_COLUMNS)
        )
            ->allowedFilters(
                AllowedFilter::exact('is_returned'),
                AllowedFilter::exact('is_parent'),
            )
            ->paginate($perPage);
    }

    public function updateQty(mixed $deliveryOrderId, mixed $deliveryOrderDetailId, mixed $qty): DeliveryOrderDetail
    {
        $deliveryOrder = $this->deliveryOrderRepository->findTenanted($deliveryOrderId, ['id', 'is_done', 'invoice_no']);
        if ($deliveryOrder->is_done) {
            throw new BadRequestHttpException('Delivery Order must be not finished. Please set as In Progress first.');
        }

        $deliveryOrderDetail = $deliveryOrder->details()->where('id', $deliveryOrderDetailId)->firstOrFail();
        $deliveryOrderDetail->update([
            'qty' => $qty,
        ]);

        return $this->loadForResource($deliveryOrderDetail);
    }

    /**
     * Known issue kept as is: the DO is loaded with id only, so the done guard never fires.
     */
    public function deleteDetail(mixed $deliveryOrderId, mixed $deliveryOrderDetailId): void
    {
        $deliveryOrder = $this->deliveryOrderRepository->findTenanted($deliveryOrderId, ['id']);
        abort_if(! auth('sanctum')->user()->tokenCan('delivery_order_delete'), 403);

        if ($deliveryOrder->is_done) {
            throw new BadRequestHttpException('Delivery Order must be not finished. Please set as In Progress first.');
        }

        $deliveryOrder->details()->where('id', $deliveryOrderDetailId)->delete();
    }

    public function resetVerifiedStock(mixed $deliveryOrderId, mixed $deliveryOrderDetailId): string
    {
        $deliveryOrder = $this->deliveryOrderRepository->findTenanted($deliveryOrderId);
        if ($deliveryOrder->is_done) {
            throw new BadRequestHttpException('Delivery Order must be not finished. Please set as In Progress first.');
        }

        $deliveryOrderDetail = $deliveryOrder->details()->select('id', 'delivery_order_id', 'sales_order_detail_id')->where('id', $deliveryOrderDetailId)->firstOrFail();
        $salesOrderDetail = $deliveryOrderDetail->salesOrderDetail()->select('id', 'product_unit_id', 'fulfilled_qty')->with('productUnit', fn ($q) => $q->select('id', 'name'))->firstOrFail();

        DB::transaction(function () use ($salesOrderDetail, $deliveryOrderDetail) {
            $salesOrderDetail->salesOrderItems()
                ->where('delivery_order_detail_id', $deliveryOrderDetail->id)
                ->whereNotReturned()
                ->orderByDesc('parent_id')
                ->delete();

            $this->salesOrderRepository->countFulfilledQty($salesOrderDetail);
        });

        return $salesOrderDetail->productUnit->name.' on DO: '.$deliveryOrder->invoice_no.' reset successfully';
    }

    private function loadForResource(DeliveryOrderDetail $deliveryOrderDetail): DeliveryOrderDetail
    {
        $deliveryOrderDetail->loadCount(['salesOrderItems as total_verified_stock' => function ($query) {
            $query->where('is_parent', false)->where('is_returned', false);
        }]);

        return $deliveryOrderDetail->load([
            'deliveryOrder' => fn ($q) => $q->withCount('mergedSalesOrders')
                ->with([
                    'reseller',
                    'mergedSalesOrders' => fn ($q) => $q->select('id', 'invoice_no', 'merged_into_sales_order_id'),
                ]),
            'salesOrderDetail' => function ($q) {
                $q->with(['warehouse', 'salesOrder', 'productUnit' => fn ($q) => $q->withTrashed()]);
            },
        ]);
    }
}
