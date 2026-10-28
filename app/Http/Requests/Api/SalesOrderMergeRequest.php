<?php

namespace App\Http\Requests\Api;

use App\Enums\Marketplace;
use App\Enums\UserType;
use App\Rules\UniqueInvoiceNumber;
use App\Services\NumberingService;
use BenSampo\Enum\Rules\EnumValue;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class SalesOrderMergeRequest extends FormRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('marketplace') && $this->input('marketplace') === '') {
            $this->merge(['marketplace' => null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sales_order_ids' => ['required', 'array', 'min:2'],
            'sales_order_ids.*' => ['required', 'integer', 'distinct', 'exists:sales_orders,id'],
            'reseller_id' => [
                'nullable',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (! DB::table('users')->where('id', $value)->where('type', UserType::Reseller)->exists()) {
                        $fail('Reseller Tidak ditemukan');
                    }
                },
            ],
            'marketplace' => ['nullable', new EnumValue(Marketplace::class)],
            'invoice_no' => $this->documentNumberRules('sales_orders'),
            'delivery_order_invoice_no' => $this->documentNumberRules('delivery_orders', ['different:invoice_no']),
            'transaction_date' => ['required', 'date_format:Y-m-d H:i:s'],
            'description' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'delivery_order_invoice_no.different' => 'Nomor DO tidak boleh sama dengan nomor SO',
        ];
    }

    /**
     * Rules of a manual document number.
     *
     * Required and unique only while the document counter is disabled: when the counter is
     * enabled the number is generated and any submitted value is ignored.
     *
     * @param  array<int, mixed>  $extraRules
     * @return array<int, mixed>
     */
    private function documentNumberRules(string $table, array $extraRules = []): array
    {
        if (NumberingService::enabled()) {
            return ['nullable'];
        }

        return ['required', ...$extraRules, new UniqueInvoiceNumber($table)];
    }
}
