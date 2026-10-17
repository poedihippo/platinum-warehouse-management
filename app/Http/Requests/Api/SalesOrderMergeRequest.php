<?php

namespace App\Http\Requests\Api;

use App\Enums\Marketplace;
use App\Enums\UserType;
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
            'transaction_date' => ['required', 'date_format:Y-m-d H:i:s'],
            'description' => ['nullable', 'string'],
        ];
    }
}
