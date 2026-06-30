<?php

namespace App\Http\Requests\Api;

use App\Models\DeliveryOrderDetail;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class DeliveryOrderDetailUpdateRequest extends FormRequest
{
    public function rules()
    {
        return [
            'qty' => [
                'required',
                'integer',
                'min:1',
                function ($attribute, $value, Closure $fail) {
                    $deliveryOrderDetailId = $this->route('deliveryOrderDetail');
                    $deliveryOrderDetail = DeliveryOrderDetail::find($deliveryOrderDetailId);

                    if (!$deliveryOrderDetail) return;

                    $salesOrderDetail = $deliveryOrderDetail->salesOrderDetail;

                    $alreadyScheduled = DeliveryOrderDetail::where('sales_order_detail_id', $salesOrderDetail->id)
                        ->where('id', '!=', $deliveryOrderDetail->id)
                        ->sum('qty');

                    $remaining = $salesOrderDetail->qty - $alreadyScheduled;

                    if ($value > $remaining) {
                        $fail("Qty melebihi sisa yang tersedia. Sisa: {$remaining}, diminta: {$value}.");
                    }
                }
            ],
        ];
    }

    public function messages()
    {
        return [
            'qty.required' => 'Qty wajib diisi.',
            'qty.min'      => 'Qty minimal 1.',
        ];
    }
}
