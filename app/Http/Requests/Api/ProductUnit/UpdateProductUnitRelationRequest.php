<?php

namespace App\Http\Requests\Api\ProductUnit;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductUnitRelationRequest extends FormRequest
{
    /**
     * Prepare inputs for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'refer_qty' => $this->refer_qty ?? $this->qty ?? null,
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
            'code' => ['sometimes', 'unique:product_units,code,' . $this->id],
            'name' => 'required',
            'description' => 'required',
            'price' => 'required',
            'refer_qty' => ['required', 'integer', 'min:1'],
            'related_product_units' => ['required', 'array'],
            'related_product_units.*.id' => ['required', 'exists:product_units,id'],
            'related_product_units.*.qty' => ['required', 'integer', 'min:0'],
        ];
    }
}
