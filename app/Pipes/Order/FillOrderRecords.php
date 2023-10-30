<?php
namespace App\Pipes\Order;

use App\Models\SalesOrder;
use App\Models\User;

class FillOrderRecords
{
    public function handle(SalesOrder $salesOrder, \Closure $next)
    {
        $records = [];

        $reseller = User::findOrFail($salesOrder->reseller_id)?->setHidden(['email_verified_at', 'remember_token', 'created_at', 'updated_at', 'deleted_at'])?->toArray() ?? [];

        $records['reseller'] = $reseller;

        $salesOrder->records = $records;

        return $next($salesOrder);
    }
}
