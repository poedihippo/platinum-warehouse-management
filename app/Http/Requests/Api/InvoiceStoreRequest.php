<?php

namespace App\Http\Requests\Api;

use App\Enums\SalesOrderType;
use App\Models\Voucher;
use App\Rules\TenantedRule;
use App\Traits\Requests\RequestToBoolean;
use BenSampo\Enum\Rules\EnumValue;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InvoiceStoreRequest extends FormRequest
{
    use RequestToBoolean;

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $items = collect($this->items)->map(function ($item) {
            return [
                ...$item,
                'warehouse_id' => $this->warehouse_id ?? null
            ];
        })->all();

        $this->merge([
            'shipment_fee' => $this->shipment_fee ? (int) $this->shipment_fee : 0,
            'additional_discount' => $this->additional_discount ? (int) $this->additional_discount : 0,
            'is_additional_discount_percentage' => $this->toBoolean($this->is_additional_discount_percentage ?? true),
            'items' => $items
            // 'is_additional_discount_percentage' => isset($this->is_additional_discount_percentage) && !is_null($this->is_additional_discount_percentage) ? $this->toBoolean($this->is_additional_discount_percentage) : true,
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
            'expected_price' => 'nullable|integer',
            'is_additional_discount_percentage' => 'required|boolean',
            'type' => ['required', new EnumValue(SalesOrderType::class)],
            'reseller_id' => [
                Rule::requiredIf(empty($this->customer_name) && empty($this->customer_phone)),
                function (string $attribute, mixed $value, Closure $fail) {
                    if (DB::table('users')->where('id', $value)->where('type', \App\Enums\UserType::CustomerEvent)->doesntExist()) {
                        $fail('Customer Tidak ditemukan');
                    }
                }
            ],
            'customer_name' => 'required_without:reseller_id',
            'customer_phone' => [
                'required_without:reseller_id',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (!empty($value) || !is_null($value) || $value != '') {
                        if (DB::table('users')->where('phone', $value)->exists()) {
                            $fail('No. Handphone sudah digunakan');
                        }
                    };
                }
            ],
            'customer_address' => 'nullable|string',
            'invoice_no' => [
                'nullable',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (DB::table('sales_orders')->whereNull('deleted_at')->where('invoice_no', trim($value))->exists()) {
                        $fail('Invoice number sudah digunakan');
                    }
                }
            ],
            'warehouse_id' => ['required', new TenantedRule()],
            'transaction_date' => 'required|date_format:Y-m-d H:i:s',
            'shipment_estimation_datetime' => 'required|date_format:Y-m-d H:i:s',
            'shipment_fee' => 'required|integer',
            'additional_discount' => 'required|integer',
            'voucher_code' => ['nullable', function (string $attribute, mixed $value, Closure $fail) {
                $voucher = Voucher::where('code', $value)->first();
                if (!$voucher) return $fail('Voucher tidak ditemukan!');
                if ($voucher?->is_used) return $fail('Voucher sudah digunakan!');
            }],
            'description' => 'nullable|string',
            'items' => [
                'required',
                'array',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (count($value) <= 0) $fail('items required');
                }
            ],
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
