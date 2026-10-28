<?php

namespace App\Http\Requests\Api;

use App\Enums\CompanyEnum;
use App\Rules\TenantedRule;
use App\Rules\UniqueInvoiceNumber;
use App\Services\NumberingService;
use BenSampo\Enum\Rules\EnumValue;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DeliveryOrderUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'company' => ['required', new EnumValue(CompanyEnum::class)],
            'invoice_no' => [
                'nullable',
                Rule::requiredIf(fn () => ! NumberingService::enabled()),
                new UniqueInvoiceNumber('delivery_orders', $this->route('delivery_order')),
            ],
            'reseller_id' => ['required', function (string $attribute, mixed $value, Closure $fail) {
                if (! DB::table('users')->where('id', $value)->where('type', \App\Enums\UserType::Reseller)->where('company', $this->company)->exists()) {
                    $fail('Reseller Tidak ditemukan');
                }
            }],
            'warehouse_id' => ['required', new TenantedRule()],
            'transaction_date' => 'required|date_format:Y-m-d H:i:s',
            'shipment_estimation_datetime' => 'required|date_format:Y-m-d H:i:s',
            'description' => 'nullable|string',
        ];
    }
}
