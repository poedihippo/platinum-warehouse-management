<?php

namespace App\Http\Requests\Api\VoucherGenerateBatch;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if ($this->generate_batch) {
            return [
                'description' => 'nullable|string',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
            ];
        }

        return [
            'voucher_category_id' => 'required|exists:voucher_categories,id',
            'description' => 'nullable|string',
            'value' => 'required|integer|min:1',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ];
    }
}
