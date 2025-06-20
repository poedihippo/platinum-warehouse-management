<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ProductUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'name' => 'required|unique:products,id,' . $this->product->id,
            'description' => 'required',
            'product_category_id' => 'required',
            'product_brand_id' => 'required',
        ];
    }
}
