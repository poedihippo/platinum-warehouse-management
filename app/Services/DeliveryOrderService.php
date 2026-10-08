<?php

namespace App\Services;

use App\Enums\CompanyEnum;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderDetail;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use App\Models\SalesOrderItem;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class DeliveryOrderService
{
    /**
     * Create a Delivery Order for a marketplace Sales Order and link every SO detail.
     *
     * No-op (null) when the Sales Order is not a marketplace order.
     */
    public static function createForSalesOrder(SalesOrder $salesOrder): ?DeliveryOrder
    {
        if (! $salesOrder->marketplace) {
            return null;
        }

        $deliveryOrder = DeliveryOrder::create([
            'warehouse_id' => $salesOrder->warehouse_id,
            'reseller_id' => $salesOrder->reseller_id,
            'company' => $salesOrder->company ?? CompanyEnum::PAS,
            'transaction_date' => $salesOrder->transaction_date ?? now(),
            'shipment_estimation_datetime' => $salesOrder->shipment_estimation_datetime ?? now(),
            'description' => $salesOrder->description,
        ]);

        static::attachSalesOrderDetails($deliveryOrder, $salesOrder->details);

        return $deliveryOrder;
    }

    /**
     * Validate that the Sales Order's referencing Delivery Order can be auto-synced.
     *
     * @return DeliveryOrder|null the single referencing Delivery Order, null when none
     */
    public static function assertEditable(SalesOrder $salesOrder): ?DeliveryOrder
    {
        $detailIds = SalesOrderDetail::where('sales_order_id', $salesOrder->id)->pluck('id');

        $deliveryOrderIds = DeliveryOrderDetail::whereIn('sales_order_detail_id', $detailIds)
            ->distinct()
            ->pluck('delivery_order_id');

        if ($deliveryOrderIds->isEmpty()) {
            return null;
        }

        if ($deliveryOrderIds->count() > 1) {
            throw new BadRequestHttpException('Sales Order direlasikan dengan lebih dari 1 Delivery Order. Hapus Delivery Order terlebih dahulu sebelum mengedit Sales Order.');
        }

        $deliveryOrder = DeliveryOrder::findOrFail($deliveryOrderIds->first());

        if ($deliveryOrder->is_done) {
            throw new BadRequestHttpException('Delivery Order sudah diselesaikan. Batalkan status Done terlebih dahulu sebelum mengedit Sales Order.');
        }

        if ($deliveryOrder->details()->whereNotIn('sales_order_detail_id', $detailIds)->exists()) {
            throw new BadRequestHttpException('Delivery Order berisi Sales Order lain. Lepas terlebih dahulu sebelum mengedit Sales Order.');
        }

        if (SalesOrderItem::whereIn('sales_order_detail_id', $detailIds)->whereNotNull('stock_id')->exists()) {
            throw new BadRequestHttpException('Terdapat stock yang sudah di-scan pada Delivery Order. Reset verifikasi terlebih dahulu sebelum mengedit Sales Order.');
        }

        return $deliveryOrder;
    }

    /**
     * Link Sales Order details into the Delivery Order (qty upsert, remaining-qty safe).
     *
     * @param  iterable<int, array{sales_order_detail_id: int, qty: int}>  $details
     * @return array{count: int, errors: array<int, array{sales_order_detail_id: int|string, message: string}>}
     */
    public static function attachDetails(DeliveryOrder $deliveryOrder, iterable $details): array
    {
        $count = 0;
        $errors = [];

        foreach ($details as $item) {
            $salesOrderDetail = SalesOrderDetail::find($item['sales_order_detail_id']);

            if (! $salesOrderDetail) {
                $errors[] = [
                    'sales_order_detail_id' => $item['sales_order_detail_id'],
                    'message' => 'Sales order detail tidak ditemukan.',
                ];

                continue;
            }

            $alreadyScheduled = DeliveryOrderDetail::where('sales_order_detail_id', $salesOrderDetail->id)
                ->where('delivery_order_id', '!=', $deliveryOrder->id)
                ->sum('qty');

            $remaining = $salesOrderDetail->qty - $alreadyScheduled;

            if ($item['qty'] > $remaining) {
                $errors[] = [
                    'sales_order_detail_id' => $salesOrderDetail->id,
                    'message' => "Qty melebihi sisa yang tersedia. Sisa: {$remaining}, diminta: {$item['qty']}",
                ];

                continue;
            }

            $existing = $deliveryOrder->details()
                ->where('sales_order_detail_id', $salesOrderDetail->id)
                ->first();

            if ($existing) {
                $existing->update(['qty' => $item['qty']]);
            } else {
                $deliveryOrder->details()->create([
                    'sales_order_detail_id' => $salesOrderDetail->id,
                    'qty' => $item['qty'],
                ]);

                $count++;
            }
        }

        return ['count' => $count, 'errors' => $errors];
    }

    /**
     * Link all given Sales Order details into the Delivery Order or fail the transaction.
     *
     * @param  Collection<int, SalesOrderDetail>  $details
     */
    public static function attachSalesOrderDetails(DeliveryOrder $deliveryOrder, Collection $details): void
    {
        $result = static::attachDetails($deliveryOrder, $details->map(fn (SalesOrderDetail $detail) => [
            'sales_order_detail_id' => $detail->id,
            'qty' => $detail->qty,
        ]));

        if (! empty($result['errors'])) {
            throw new BadRequestHttpException($result['errors'][0]['message']);
        }
    }

    /**
     * Keep the Delivery Order header in sync with its Sales Order.
     */
    public static function syncHeader(DeliveryOrder $deliveryOrder, SalesOrder $salesOrder): void
    {
        $deliveryOrder->update([
            'warehouse_id' => $salesOrder->warehouse_id,
            'reseller_id' => $salesOrder->reseller_id,
            'company' => $salesOrder->company ?? CompanyEnum::PAS,
            'transaction_date' => $salesOrder->transaction_date ?? $deliveryOrder->transaction_date,
            'shipment_estimation_datetime' => $salesOrder->shipment_estimation_datetime ?? $deliveryOrder->shipment_estimation_datetime,
            'description' => $salesOrder->description ?? $deliveryOrder->description,
        ]);
    }

    /**
     * Remove sales_order_items that reference the given Sales Order details.
     *
     * @param  Collection<int, int>  $salesOrderDetailIds
     */
    public static function detachSalesOrderItems(Collection $salesOrderDetailIds): void
    {
        SalesOrderItem::whereIn('sales_order_detail_id', $salesOrderDetailIds)->delete();
    }

    /**
     * Delete a Delivery Order together with its sales_order_items references.
     */
    public static function deleteDeliveryOrder(DeliveryOrder $deliveryOrder): void
    {
        $deliveryOrderDetailIds = $deliveryOrder->details()->pluck('id');

        SalesOrderItem::whereIn('delivery_order_detail_id', $deliveryOrderDetailIds)->delete();
        $deliveryOrder->delete();
    }
}
