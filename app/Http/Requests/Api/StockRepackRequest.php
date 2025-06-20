<?php

namespace App\Http\Requests\Api;

use App\Models\StockProductUnit;
use App\Rules\TenantedRule;
use Illuminate\Foundation\Http\FormRequest;

class StockRepackRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'qty' => 'required|integer|min:1',
            // 'stock_product_unit_id' => 'required|exists:stock_product_units,id',
            'stock_product_unit_id' => ['required', new TenantedRule(StockProductUnit::class)],
            'created_at' => 'nullable|date',
        ];
    }
}
