<?php

namespace App\Http\Requests\Api;

use App\Models\StockProductUnit;
use App\Rules\TenantedRule;
use Illuminate\Foundation\Http\FormRequest;

class AdjustmentRequestStoreRequest extends FormRequest
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
            'stock_product_unit_id' => ['required', new TenantedRule(StockProductUnit::class)],
            'value' => 'required|integer|min:1',
            'is_increment' => 'nullable|boolean',
            // 'is_approved' => 'nullable|boolean',
            'description' => 'nullable',
            'reason' => 'nullable',
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
            'is_increment' => $this->toBoolean($this->is_increment),
            // // 'is_approved' => $this->toBoolean($this->is_approved),
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
