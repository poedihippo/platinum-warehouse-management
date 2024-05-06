<?php

namespace App\Http\Requests\Api\VoucherCategory;

use App\Enums\DiscountType;
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
            'name' => 'required|string',
            'discount_type' => ['required', new EnumValue(DiscountType::class, false)],
            'discount_amount' => 'required|numeric',
            'description' => 'nullable|string',
        ];
    }
}
