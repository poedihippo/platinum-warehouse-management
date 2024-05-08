<?php

namespace App\Http\Requests\Api\VoucherGenerateBatch;

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
        if ($this->generate_batch) {
            return [
                'description' => 'nullable|string',
            ];
        }

        return [
            'voucher_category_id' => 'required|exists:voucher_categories,id',
            'description' => 'nullable|string',
            'value' => 'required|integer|min:1',
        ];
    }
}
