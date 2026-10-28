<?php

namespace App\Http\Requests\Api;

use App\Enums\CompanyEnum;
use App\Enums\Marketplace;
use App\Enums\SalesOrderType;
use App\Rules\TenantedRule;
use App\Rules\UniqueInvoiceNumber;
use App\Services\NumberingService;
use BenSampo\Enum\Rules\EnumValue;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SalesOrderUpdateRequest extends FormRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'shipment_fee' => $this->shipment_fee ? (int) $this->shipment_fee : 0,
            'additional_discount' => $this->additional_discount ? (int) $this->additional_discount : 0,
        ]);

        if ($this->has('marketplace') && $this->input('marketplace') === '') {
            $this->merge(['marketplace' => null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'company' => ['required', new EnumValue(CompanyEnum::class)],
            'expected_price' => 'nullable|integer',
            'type' => ['nullable', new EnumValue(SalesOrderType::class)],
            'marketplace' => ['nullable', new EnumValue(Marketplace::class)],
            'reseller_id' => [
                'nullable',
                'required_without:marketplace',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (! DB::table('users')->where('id', $value)->where('type', \App\Enums\UserType::Reseller)->where('company', $this->company)->exists()) {
                        $fail('Reseller Tidak ditemukan');
                    }
                },
            ],
            'invoice_no' => [
                'nullable',
                Rule::requiredIf(fn () => ! NumberingService::enabled()),
                new UniqueInvoiceNumber('sales_orders', $this->route('sales_order')),
            ],
            // 'warehouse_id' => ['required', new TenantedRule()],
            'transaction_date' => ['nullable', 'required_without:marketplace', 'date_format:Y-m-d H:i:s'],
            'shipment_estimation_datetime' => ['nullable', 'required_without:marketplace', 'date_format:Y-m-d H:i:s'],
            'shipment_fee' => 'required|integer',
            'additional_discount' => 'required|integer',
            'description' => 'nullable|string',
            'items' => ['required', 'array', function (string $attribute, mixed $value, Closure $fail) {
                if (count($value) <= 0) {
                    $fail('items required');
                }
            }],
            'items.*.product_unit_id' => 'required|integer|exists:product_units,id',
            'items.*.qty' => 'required|integer',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'required|numeric|min:0',
            'items.*.tax' => 'required|boolean',
            'items.*.total_price' => 'required|numeric|min:0',
            'items.*.warehouse_id' => ['required', new TenantedRule()],
        ];
    }
}
