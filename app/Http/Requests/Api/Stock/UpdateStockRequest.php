<?php

namespace App\Http\Requests\Api\Stock;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStockRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            // 'batch_number' => 'nullable|string',
            // 'batch_number_jp' => 'nullable|string',
            // 'stock_product_unit_id' => 'required|exists:stock_product_units,id',
            'expired_date' => 'nullable|date',
        ];
    }
}
