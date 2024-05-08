<?php

namespace App\Http\Requests\Api;

use App\Models\ReceiveOrderDetail;
use App\Models\StockProductUnit;
use App\Rules\TenantedRule;
use Illuminate\Foundation\Http\FormRequest;

class StockRecordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'is_print_all' => 'nullable|boolean',
            // 'stock_product_unit_id' => 'nullable|exists:stock_product_units,id',
            // 'receive_order_detail_id' => 'nullable|exists:receive_order_details,id',
            'stock_product_unit_id' => ['nullable', new TenantedRule(StockProductUnit::class)],
            'receive_order_detail_id' => ['nullable', new TenantedRule(ReceiveOrderDetail::class)],
            'stock_ids' => 'nullable|array',
            'stock_ids.*' => 'exists:stocks,id',
        ];
    }

    /**
     * Prepare inputs for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'is_print_all' => $this->toBoolean($this->is_print_all),
        ]);
    }

    /**
     * Convert to boolean
     *
     * @param $booleable
     * @return boolean
     */
    private function toBoolean($booleable)
    {
        return filter_var($booleable, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
}
