<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ProductUnitStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->tokenCan('product_unit_create');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'code' => 'required|unique:product_units,code',
            'uom_id' => 'required|exists:uoms,id',
            'name' => 'required',
            'description' => 'required',
            'product_id' => 'required',
            'price' => 'required',
            'packaging_id' => 'nullable|exists:product_units,id',
        ];
    }
}
