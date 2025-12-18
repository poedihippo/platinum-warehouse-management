<?php

namespace App\Http\Requests\Api;

use App\Enums\CompanyEnum;
use BenSampo\Enum\Rules\EnumValue;
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
            'company' => ['required', new EnumValue(CompanyEnum::class)],
            'name' => ['required', 'unique:products,name,' . $this->product->id],
            // 'description' => 'required',
            'product_category_id' => ['required', 'exists:product_categories,id'],
            'product_brand_id' => ['required', 'exists:product_brands,id'],
        ];
    }
}
