<?php

namespace App\Http\Requests\Api;

use App\Rules\TenantedRule;
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
        return true;
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
            //     if (!$salesOrder) $fail('Sales order Tidak ditemukan');
            //     if ($salesOrder->deliveryOrder) $fail("Can't select a sales order that already has a delivery order");
            // }],
            'invoice_no' => [
                'nullable',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (DB::table('delivery_orders')->whereNull('deleted_at')->where('invoice_no', trim($value))->exists()) {
                        $fail('Invoice number sudah digunakan');
                    }
                }
            ],
            'reseller_id' => ['required', function (string $attribute, mixed $value, Closure $fail) {
                if (!DB::table('users')->where('id', $value)->where('type', \App\Enums\UserType::Reseller)->exists()) {
                    $fail('Reseller Tidak ditemukan');
                }
            }],
            'warehouse_id' => ['required', new TenantedRule()],
            'transaction_date' => 'required|date_format:Y-m-d H:i:s',
            'shipment_estimation_datetime' => 'required|date_format:Y-m-d H:i:s',
            'description' => 'nullable|string',
        ];
    }
}
