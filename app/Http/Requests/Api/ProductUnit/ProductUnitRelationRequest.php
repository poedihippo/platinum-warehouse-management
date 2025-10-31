<?php

namespace App\Http\Requests\Api\ProductUnit;

use Illuminate\Foundation\Http\FormRequest;

class ProductUnitRelationRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'code' => 'required|unique:product_units,code',
            'name' => 'required',
            'description' => 'required',
            'price' => 'required',
            'related_product_units' => ['required', 'array'],
            'related_product_units.*.id' => ['required', 'exists:product_units,id'],
            'related_product_units.*.qty' => ['required', 'integer', 'min:0'],
        ];
    }
}
