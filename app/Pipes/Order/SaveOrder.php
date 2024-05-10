<?php

namespace App\Pipes\Order;

use App\Enums\UserType;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SaveOrder
{
    public function handle(SalesOrder $salesOrder, \Closure $next)
    {
        $salesOrder = DB::transaction(function () use ($salesOrder) {
            if (
                isset($salesOrder->raw_source['customer_name']) &&
                $salesOrder->raw_source['customer_name'] != '' &&
                $reseller = $this->createReseller($salesOrder)
            ) {
                $salesOrder->reseller_id = $reseller->id;

                $records = $salesOrder->records ?? [];

                if ($reseller) $records['reseller'] = $reseller->setHidden(['email_verified_at', 'remember_token', 'created_at', 'updated_at', 'deleted_at'])?->toArray() ?? [];

                $salesOrder->records = $records;
            }

            $salesOrderDetails = $salesOrder->sales_order_details;
            unset($salesOrder->sales_order_details);
            $salesOrder->save();
            $salesOrder->details()->saveMany($salesOrderDetails);

            return $salesOrder;
        });

        return $next($salesOrder);
    }

    public function createReseller(SalesOrder $salesOrder): User|null
    {
        $rawSoruce = $salesOrder->raw_source;

        try {
            return User::create([
                'name' => $rawSoruce['customer_name'],
                'phone' => $rawSoruce['customer_phone'],
                'address' => $rawSoruce['customer_address'],
                'type' => UserType::Customer,
            ]);
        } catch (\Exception $e) {
            return null;
        }
    }
}
