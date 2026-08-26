<?php

namespace App\Http\Requests\Api\Voucher;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if ($this->voucher) {
            return $this->updateRules();
        }

        return $this->createRules();
    }

    private function createRules(): array
    {
        return [
            'voucher_generate_batch_id' => 'nullable|exists:voucher_generate_batches,id',
            'voucher_category_id' => 'required|exists:voucher_categories,id',
            'code' => 'nullable|unique:vouchers,code',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ];
    }

    private function updateRules(): array
    {
        $voucher = $this->voucher;

        return [
            'voucher_generate_batch_id' => 'nullable|exists:voucher_generate_batches,id',
            'voucher_category_id' => 'required|exists:voucher_categories,id',
            'code' => 'required|unique:vouchers,code,'.$voucher->id,
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ];
    }
}
