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
            'code' => 'required|unique:sales_orders,code',
            'reseller_id' => 'required|exists:users,id',
            'transaction_date' => 'required|date_format:Y-m-d H:i:s',
            'product_unit_ids' => 'required|array',
            'product_unit_ids.*' => 'exists:product_units,id',
            'qty' => 'required|array',
            'qty.*' => 'integer',
        ];
    }
}
