<?php

namespace App\Http\Requests\Api\Payment;

use App\Enums\PaymentType;
use BenSampo\Enum\Rules\EnumValue;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sales_order_id' => 'required|exists:sales_orders,id',
            'amount' => 'required|numeric',
            'type' => ['required', new EnumValue(PaymentType::class, false)],
            'note' => 'nullable|string',
            'files' => 'nullable|array',
            'files.*' => 'required|mimes:jpg,jpeg,png,doc,docx,pdf|max:10240',
        ];
    }
}
