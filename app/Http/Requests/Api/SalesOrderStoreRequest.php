<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SalesOrderStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return user()->tokenCan('sales_order_create');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'invoice_no' => 'required|unique:sales_orders,invoice_no',
            'code' => 'required|unique:sales_orders,code',
            'reseller_id' => 'required|exists:users,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'transaction_date' => 'required|date_format:Y-m-d H:i:s',
            'shipment_estimation_datetime' => 'required|date_format:Y-m-d H:i:s',
            'note' => 'nullable|string',
            'product_unit_ids' => 'required|array',
            'product_unit_ids.*' => 'exists:product_units,id',
            'qty' => 'required|array',
            'qty.*' => 'integer',
        ];
    }
}
