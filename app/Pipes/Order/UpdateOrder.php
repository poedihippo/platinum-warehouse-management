<?php

namespace App\Pipes\Order;

use App\Models\DeliveryOrderDetail;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use App\Services\DeliveryOrderService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class UpdateOrder
{
    public function handle(SalesOrder $salesOrder, \Closure $next)
    {
        $salesOrder = DB::transaction(function () use ($salesOrder) {
            $wasMarketplace = $salesOrder->getRawOriginal('marketplace') !== null;
            $deliveryOrder = null;
            $lockedDetailIds = collect();
            $oldDetailIds = SalesOrderDetail::where('sales_order_id', $salesOrder->id)->pluck('id');

            if ($wasMarketplace) {
                $deliveryOrder = DeliveryOrderService::assertEditable($salesOrder);
                $lockedDetailIds = DeliveryOrderService::lockedSalesOrderDetailIds($salesOrder);

                if (! $salesOrder->marketplace && $lockedDetailIds->isNotEmpty()) {
                    $this->throwVerifiedDetail();
                }
            }

            $newDetails = $salesOrder->details->values();
            unset($salesOrder->details);

            $salesOrder->save();

            if ($wasMarketplace) {
                $this->syncDetailsInPlace($salesOrder, $oldDetailIds, $newDetails, $lockedDetailIds);
            } else {
                if (DeliveryOrderDetail::whereIn('sales_order_detail_id', $oldDetailIds)->exists()) {
                    throw new BadRequestHttpException('DO harus dihapus terlebih dahulu sebelum mengedit SO');
                }

                SalesOrderDetail::whereIn('id', $oldDetailIds)->delete();
                $salesOrder->details()->saveMany($newDetails);
            }

            if (isset($salesOrder->vouchers_ids_to_sync) && ! empty($salesOrder->vouchers_ids_to_sync)) {
                $salesOrder->vouchers()->sync($this->voucherPivot($salesOrder));
                unset($salesOrder->vouchers_ids_to_sync, $salesOrder->vouchers_discount_amount);
            }

            if ($deliveryOrder && ! $salesOrder->marketplace) {
                DeliveryOrderService::deleteDeliveryOrder($deliveryOrder);
            } elseif ($deliveryOrder) {
                DeliveryOrderService::syncHeader($deliveryOrder, $salesOrder);
                DeliveryOrderService::attachSalesOrderDetails($deliveryOrder, $salesOrder->details()->get());
            }

            return $salesOrder;
        });

        return $next($salesOrder);
    }

    /**
     * Update Sales Order details in place: unchanged rows keep their IDs, changed rows
     * are updated, new rows are inserted and removed rows are deleted.
     *
     * Details that already have a verified (scanned) stock item are locked: they must be
     * submitted unchanged and can never be removed.
     *
     * @param  Collection<int, int>  $oldDetailIds
     * @param  Collection<int, SalesOrderDetail>  $newDetails
     * @param  Collection<int, int>  $lockedDetailIds
     */
    private function syncDetailsInPlace(SalesOrder $salesOrder, Collection $oldDetailIds, Collection $newDetails, Collection $lockedDetailIds): void
    {
        $oldByItem = SalesOrderDetail::whereIn('id', $oldDetailIds)->get()
            ->groupBy(fn (SalesOrderDetail $detail) => $detail->product_unit_id.'|'.($detail->warehouse_id ?? ''))
            ->map(fn (Collection $details) => $details->values());

        $matched = collect();
        $toCreate = collect();

        foreach ($newDetails as $newDetail) {
            $key = $newDetail->product_unit_id.'|'.($newDetail->warehouse_id ?? '');
            $oldDetail = $oldByItem->get($key)?->shift();

            if ($oldDetail) {
                $matched->push([$oldDetail, $newDetail]);
            } else {
                $toCreate->push($newDetail);
            }
        }

        foreach ($matched as [$oldDetail, $newDetail]) {
            if ($lockedDetailIds->contains($oldDetail->id)) {
                continue;
            }

            $oldDetail->qty = $newDetail->qty;
            $oldDetail->unit_price = $newDetail->unit_price;
            $oldDetail->discount = $newDetail->discount;
            $oldDetail->tax = $newDetail->tax;
            $oldDetail->total_price = $newDetail->total_price;
            $oldDetail->save();
        }

        foreach ($oldByItem->flatten(1) as $removedDetail) {
            if ($lockedDetailIds->contains($removedDetail->id)) {
                $this->throwVerifiedDetail();
            }

            DeliveryOrderDetail::where('sales_order_detail_id', $removedDetail->id)->delete();
            $removedDetail->delete();
        }

        if ($toCreate->isNotEmpty()) {
            $salesOrder->details()->saveMany($toCreate);
        }
    }

    private function throwVerifiedDetail(): void
    {
        throw new BadRequestHttpException(DeliveryOrderService::VERIFIED_STOCK_MESSAGE);
    }

    private function voucherPivot(SalesOrder $salesOrder): array
    {
        $amounts = $salesOrder->vouchers_discount_amount ?? [];

        return collect($salesOrder->vouchers_ids_to_sync)
            ->mapWithKeys(fn ($voucherId) => [
                $voucherId => ['discount_amount' => $amounts[$voucherId] ?? 0],
            ])
            ->all();
    }
}
