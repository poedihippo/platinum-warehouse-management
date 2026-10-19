<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryOrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return array_merge(
            parent::toArray($request),
            [
                'is_marketplace_source' => ! is_null($this->sales_order_id),
                'is_merged_sales_order' => (bool) (($this->merged_sales_orders_count ?? 0) > 0),
                'merged_sales_orders_count' => (int) ($this->merged_sales_orders_count ?? 0),
                'merged_sales_orders' => $this->whenLoaded(
                    'mergedSalesOrders',
                    fn () => $this->mergedSalesOrders->map(fn ($salesOrder) => [
                        'id' => $salesOrder->id,
                        'merged_into_sales_order_id' => $salesOrder->merged_into_sales_order_id,
                        'invoice_no' => $salesOrder->invoice_no,
                    ])->values()
                ),
                // 'sales_order' => new SalesOrderResource($this->whenLoaded('salesOrder')->loadCount('details')),
                'user' => new UserResource($this->whenLoaded('user')),
                'reseller' => new UserResource($this->reseller),
            ]
        );
    }
}
