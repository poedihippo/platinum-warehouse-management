<?php

namespace App\Http\Requests\Api;

use App\Models\SalesOrderDetail;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class DeliveryOrderAttachRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'sales_order_detail_ids' => 'required|array',
            'sales_order_detail_ids.*' => ['integer', 'exists:sales_order_details,id', function ($attribute, $value, Closure $fail) {
                $salesOrderDetail = SalesOrderDetail::find($value);
                if ($salesOrderDetail?->deliveryOrderDetail) $fail('Sales order data has been used in another delivery order');
            }],
        ];
    }
}
