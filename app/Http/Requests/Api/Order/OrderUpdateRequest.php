<?php

namespace App\Http\Requests\Api\Order;

use App\Enums\SalesOrderType;
use App\Rules\TenantedRule;
use BenSampo\Enum\Rules\EnumValue;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class OrderUpdateRequest extends FormRequest
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
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'shipment_fee' => $this->shipment_fee ? (int) $this->shipment_fee : 0,
            'additional_discount' => $this->additional_discount ? (int) $this->additional_discount : 0,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        $Order = $this->sales_order;
        return [
            'expected_price' => 'nullable|integer',
            'type' => ['nullable', new EnumValue(SalesOrderType::class)],
            'reseller_id' => ['required', function (string $attribute, mixed $value, Closure $fail) {
                if (!DB::table('users')->where('id', $value)->where('type', \App\Enums\UserType::Reseller)->exists()) {
                    $fail('Reseller Tidak ditemukan');
                }
            }],
            'invoice_no' => [
                'nullable',
                function (string $attribute, mixed $value, Closure $fail) use($Order) {
                    if (DB::table('sales_orders')->whereNull('deleted_at')->where('id', '!=', $Order->id)->where('invoice_no', trim($value))->exists()) {
                        $fail('Invoice number sudah digunakan');
                    }
                }
            ],
            // 'warehouse_id' => ['required', new TenantedRule()],
            'transaction_date' => 'required|date_format:Y-m-d H:i:s',
            'shipment_estimation_datetime' => 'required|date_format:Y-m-d H:i:s',
            'shipment_fee' => 'required|integer',
            'additional_discount' => 'required|integer',
            'description' => 'nullable|string',
            'items' => ['required', 'array', function (string $attribute, mixed $value, Closure $fail) {
                if (count($value) <= 0) $fail('items required');
            }],
            'items.*.product_unit_id' => 'required|integer|exists:product_units,id',
            'items.*.packaging_id' => 'nullable|integer|exists:product_units,id',
            'items.*.qty' => 'required|integer',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'required|numeric|min:0',
            'items.*.tax' => 'required|boolean',
            'items.*.total_price' => 'required|numeric|min:0',
            'items.*.warehouse_id' => ['required', new TenantedRule()],
        ];
    }
}
