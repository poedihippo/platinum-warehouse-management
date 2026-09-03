<?php

namespace App\Pipes\Order;

use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use Illuminate\Support\Facades\DB;

class UpdateOrder
{
    public function handle(SalesOrder $salesOrder, \Closure $next)
    {
        $salesOrder = DB::transaction(function () use ($salesOrder) {
            SalesOrderDetail::where('sales_order_id', $salesOrder->id)->delete();

            $salesOrderDetails = $salesOrder->details;
            unset($salesOrder->details);

            $salesOrder->save();
            $salesOrder->details()->saveMany($salesOrderDetails);

            if (isset($salesOrder->vouchers_ids_to_sync) && ! empty($salesOrder->vouchers_ids_to_sync)) {
                $salesOrder->vouchers()->sync($this->voucherPivot($salesOrder));
                unset($salesOrder->vouchers_ids_to_sync, $salesOrder->vouchers_discount_amount);
            }

            return $salesOrder;
        });

        return $next($salesOrder);
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
