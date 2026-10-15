<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SalesOrderResource extends JsonResource
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
                'cashier_name' => $this->cashier_name,
                'total_roll_dice' => floor($this->price / config('app.min_price_roll_dice', 500000)),
                'has_delivery_order' => $this->has_delivery_order,
                'is_merge_sales_order' => $this->isMergeSalesOrder(),
                'can_be_merged' => ! is_null($this->marketplace)
                    && is_null($this->merged_into_sales_order_id)
                    && ($this->merged_sales_orders_count ?? 0) === 0
                    && ($this->delivery_orders_count ?? 0) > 0
                    && ($this->pending_delivery_orders_count ?? 0) === 0,
                'merged_source_ids' => $this->whenLoaded('mergedSalesOrders', fn () => $this->mergedSalesOrders->pluck('id')->values()),
                'delivery_order_id' => $this->whenLoaded(
                    'deliveryOrderDetails',
                    fn () => $this->deliveryOrderDetails->first()?->delivery_order_id
                ),
                'details_count' => $this->whenCounted('details'),
                'auto_discount_details' => $this->auto_discount_details,
                'vouchers' => $this->whenLoaded('vouchers', fn () => $this->vouchers->map(fn ($v) => [
                    'id' => $v->id,
                    'code' => $v->code,
                    'pivot' => [
                        'discount_amount' => $v->pivot->discount_amount,
                    ],
                    'category' => $v->category ? [
                        'id' => $v->category->id,
                        'name' => $v->category->name,
                        'discount_type' => $v->category->discount_type->value ?? null,
                        'discount_amount' => $v->category->discount_amount,
                    ] : null,
                ])),
                'warehouse' => new WarehouseResource($this->whenLoaded('warehouse')),
                'user' => new UserResource($this->whenLoaded('user')),
                'reseller' => new UserResource($this->reseller),
                'details' => SalesOrderDetailResource::collection($this->whenLoaded('details')),
            ]
        );
    }
}
