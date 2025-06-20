<?php

namespace App\Http\Requests\Api\Stock;

use Illuminate\Foundation\Http\FormRequest;

class GroupingRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'expired_date' => 'nullable|date',
            'total_group' => 'required|integer|gt:0',
            'qty' => 'required|integer|gt:0',
            // 'warehouse_id' => 'required|exists:warehouses,id',
            'stock_product_unit_id' => 'required_without:receive_order_detail_id|missing_with:receive_order_detail_id|exists:stock_product_units,id',
            'receive_order_detail_id' => 'required_without:stock_product_unit_id|missing_with:stock_product_unit_id|exists:receive_order_details,id',
        ];
    }
}
