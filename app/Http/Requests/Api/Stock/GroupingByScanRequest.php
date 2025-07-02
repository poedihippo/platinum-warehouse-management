<?php

namespace App\Http\Requests\Api\Stock;

use App\Models\Stock;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class GroupingByScanRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'name' => 'nullable|string|unique:stocks,description',
            'stock_product_unit_id' => 'required|exists:stock_product_units,id',
            'expired_date' => 'nullable|date',
            'ids' => 'required|array',
            'ids.*' => ['required', function ($attribute, string $value, Closure $fail) {
                $stock = Stock::select('id', 'stock_product_unit_id', 'parent_id')->where('id', $value)->first();
                if (!$stock) {
                    return $fail('QR tidak ditemukan');
                }

                if ($stock->stock_product_unit_id != $this->stock_product_unit_id) {
                    return $fail('QR tidak sesuai dengan product unit');
                }

                if ($stock->parent_id) {
                    return $fail('QR sudah digruping');
                }
            }]
        ];
    }
}
