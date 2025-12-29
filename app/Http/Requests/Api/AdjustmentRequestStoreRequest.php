<?php

namespace App\Http\Requests\Api;

use App\Models\StockProductUnit;
use App\Rules\TenantedRule;
use App\Traits\Requests\RequestToBoolean;
use Illuminate\Foundation\Http\FormRequest;

class AdjustmentRequestStoreRequest extends FormRequest
{
    use RequestToBoolean;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_increment' => $this->toBoolean($this->is_increment ?? 1),
        ]);
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
            'expired_date' => 'required|date',
            'is_increment' => 'nullable|boolean',
            // 'is_approved' => 'nullable|boolean',
            'description' => 'nullable',
            'reason' => 'nullable',
        ];
    }
}
