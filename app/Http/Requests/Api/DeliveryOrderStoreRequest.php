<?php

namespace App\Http\Requests\Api;

use App\Models\SalesOrder;
use Closure;
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
            'sales_order_id' => ['required', function ($attribute, $value, Closure $fail) {
                $salesOrder = SalesOrder::find($value);
                if (!$salesOrder) $fail('Sales order not found');
                if ($salesOrder->deliveryOrder) $fail("Can't select a sales order that already has a delivery order");
            }],
            'description' => 'nullable|string',
        ];
    }
}
