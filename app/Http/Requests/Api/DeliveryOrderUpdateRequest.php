<?php

namespace App\Http\Requests\Api;

use App\Enums\CompanyEnum;
use App\Rules\TenantedRule;
use BenSampo\Enum\Rules\EnumValue;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class DeliveryOrderUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        $deliveryOrder = $this->delivery_order;
        return [
            'company' => ['required', new EnumValue(CompanyEnum::class)],
            'invoice_no' => [
                'nullable',
                function (string $attribute, mixed $value, Closure $fail) use($deliveryOrder) {
                    if (DB::table('delivery_orders')->whereNull('deleted_at')->where('id', '!=', $deliveryOrder->id)->where('invoice_no', trim($value))->exists()) {
                        $fail('Invoice number sudah digunakan');
                    }
                }
            ],
            'reseller_id' => ['required', function (string $attribute, mixed $value, Closure $fail) {
                if (!DB::table('users')->where('id', $value)->where('type', \App\Enums\UserType::Reseller)->exists()) {
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
