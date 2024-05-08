<?php

namespace App\Http\Requests\Api\Payment;

use App\Enums\PaymentType;
use App\Models\SalesOrder;
use App\Rules\TenantedRule;
use App\Traits\Requests\RequestToBoolean;
use BenSampo\Enum\Rules\EnumValue;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    use RequestToBoolean;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare inputs for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'is_full_payment' => $this->toBoolean($this->is_full_payment),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sales_order_id' => ['required', new TenantedRule(SalesOrder::class)],
            'amount' => 'required|numeric',
            'type' => ['required', new EnumValue(PaymentType::class, false)],
            'note' => 'nullable|string',
            'is_full_payment' => 'nullable|boolean',
            'files' => 'nullable|array',
            'files.*' => 'required|mimes:jpg,jpeg,png,doc,docx,pdf|max:10240',
        ];
    }
}
