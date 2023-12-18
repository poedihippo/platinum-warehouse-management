<?php

namespace App\Http\Requests\Api;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class DeliveryOrderStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->tokenCan('delivery_order_create');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            // 'sales_order_id' => ['required', function ($attribute, $value, Closure $fail) {
            //     $salesOrder = SalesOrder::find($value);
            //     if (!$salesOrder) $fail('Sales order not found');
            //     if ($salesOrder->deliveryOrder) $fail("Can't select a sales order that already has a delivery order");
            // }],
            'invoice_no' => [
                'nullable',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (DB::table('delivery_orders')->where('invoice_no', trim($value))->exists()) {
                        $fail('Invoice number is already in use');
                    }
                }
            ],
            'reseller_id' => ['required', function (string $attribute, mixed $value, Closure $fail) {
                if (!DB::table('users')->where('id', $value)->where('type', \App\Enums\UserType::Reseller)->exists()) {
                    $fail('Reseller not found');
                }
            }],
            'warehouse_id' => 'required|exists:warehouses,id',
            'transaction_date' => 'required|date_format:Y-m-d H:i:s',
            'shipment_estimation_datetime' => 'required|date_format:Y-m-d H:i:s',
            'description' => 'nullable|string',
        ];
    }
}
