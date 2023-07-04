<?php

namespace App\Http\Requests\Api;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class SalesOrderStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->tokenCan('sales_order_create');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'reseller_id' => ['required', function (string $attribute, mixed $value, Closure $fail) {
                if (!DB::table('users')->where('id', $value)->where('type', \App\Enums\UserType::Reseller)->exists()) {
                    $fail('Reseller not found');
                }
            }],
            'warehouse_id' => 'required|exists:warehouses,id',
            'transaction_date' => 'required|date_format:Y-m-d H:i:s',
            'shipment_estimation_datetime' => 'required|date_format:Y-m-d H:i:s',
            'description' => 'nullable|string',
            'items' => ['required', 'array', function (string $attribute, mixed $value, Closure $fail) {
                if (count($value) <= 0) $fail('items required');
            }],
            'items.*.product_unit_id' => 'required|integer|exists:product_units,id',
            'items.*.qty' => 'required|integer',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'required|numeric|min:0',
            'items.*.tax' => 'nullable|boolean',
            'items.*.total_price' => 'required|numeric|min:0',
            'items.*.warehouse_id' => 'required|exists:warehouses,id',
        ];
    }
}
