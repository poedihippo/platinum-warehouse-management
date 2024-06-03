<?php

namespace App\Http\Requests\Api\Order;

use App\Enums\SalesOrderType;
use App\Traits\Requests\RequestToBoolean;
use BenSampo\Enum\Rules\EnumValue;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderStoreRequest extends FormRequest
{
    use RequestToBoolean;

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
            'is_additional_discount_percentage' => $this->toBoolean($this->is_additional_discount_percentage ?? true),
            'spg_id' => auth()->id(),
            'transaction_date' => $this->transaction_date ?? date('Y-m-d H:i:s'),
            'shipment_estimation_datetime' => $this->shipment_estimation_datetime ?? date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'spg_id' => 'required|exists:users,id',
            'expected_price' => 'nullable|integer',
            'is_additional_discount_percentage' => 'required|boolean',
            'type' => ['required', new EnumValue(SalesOrderType::class)],
            'customer_id' => [
                Rule::requiredIf(empty($this->customer_name) && empty($this->customer_phone)),
                function (string $attribute, mixed $value, Closure $fail) {
                    if (DB::table('users')->where('id', $value)->where('type', \App\Enums\UserType::CustomerEvent)->doesntExist()) {
                        $fail('Customer Tidak ditemukan');
                    }
                }
            ],
            'customer_name' => 'required_without:customer_id',
            'customer_phone' => [
                'required_without:customer_id',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (!empty($value) || !is_null($value) || $value != '') {
                        if (DB::table('users')->where('phone', $value)->exists()) {
                            $fail('No. Handphone sudah digunakan');
                        }
                    };
                }
            ],
            'customer_address' => 'nullable|string',
            // 'invoice_no' => [
            //     'nullable',
            //     function (string $attribute, mixed $value, Closure $fail) {
            //         if (DB::table('sales_orders')->whereNull('deleted_at')->where('invoice_no', trim($value))->exists()) {
            //             $fail('Invoice number sudah digunakan');
            //         }
            //     }
            // ],
            // 'warehouse_id' => ['required', new TenantedRule()],
            'transaction_date' => 'required|date_format:Y-m-d H:i:s',
            'shipment_estimation_datetime' => 'required|date_format:Y-m-d H:i:s',
            'shipment_fee' => 'required|integer',
            'additional_discount' => 'required|integer',
            // 'voucher_code' => ['nullable', function (string $attribute, mixed $value, Closure $fail) {
            //     $voucher = Voucher::where('code', $value)->first();
            //     if (!$voucher) $fail('Voucher tidak ditemukan!');
            //     if ($voucher?->is_used) $fail('Voucher sudah digunakan!');
            // }],
            'description' => 'nullable|string',
            'items' => [
                'required',
                'array',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (count($value) <= 0) $fail('items required');
                }
            ],
            'items.*.product_unit_id' => 'required|integer|exists:product_units,id',
            // 'items.*.packaging_id' => 'nullable|integer|exists:product_units,id',
            'items.*.qty' => 'required|integer',
            // 'items.*.unit_price' => 'required|numeric|min:0',
            // 'items.*.discount' => 'required|numeric|min:0',
            // 'items.*.tax' => 'required|boolean',
            // 'items.*.total_price' => 'required|numeric|min:0',
            // 'items.*.warehouse_id' => ['required'],
        ];
    }
}
