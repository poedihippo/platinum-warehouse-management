<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveOrderDetailStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->tokenCan('receive_order_detail_create');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'product_unit_id' => 'required|exists:product_units,id',
            'qty' => 'required|integer',
            'item_unit' => 'required',
            'bruto_unit_price' => 'required|integer',
        ];
    }
}
