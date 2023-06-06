<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

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
            'sales_order_id' => 'required|exists:sales_orders,id',
            'invoice_no' => 'required|unique:delivery_orders,invoice_no',
            'code' => 'required|unique:delivery_orders,code',
            'description' => 'nullable|string',
        ];
    }
}
