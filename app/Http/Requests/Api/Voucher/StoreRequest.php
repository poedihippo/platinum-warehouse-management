<?php

namespace App\Http\Requests\Api\Voucher;

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
        if ($voucher = $this->voucher) {
            return [
                'voucher_generate_batch_id' => 'required|exists:voucher_generate_batches,id',
                'voucher_category_id' => 'required|exists:voucher_categories,id',
                'code' => 'required|unique:vouchers,code,'.$voucher->id,
                'description' => 'nullable|string',
            ];
        }

        return [
            'voucher_generate_batch_id' => 'required|exists:voucher_generate_batches,id',
            'voucher_category_id' => 'required|exists:voucher_categories,id',
            'code' => 'required|unique:vouchers,code',
            'description' => 'nullable|string',
        ];
    }
}
