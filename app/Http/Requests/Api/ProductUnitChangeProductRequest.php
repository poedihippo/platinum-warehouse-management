<?php

namespace App\Http\Requests\Api;

use App\Enums\CompanyEnum;
use App\Models\Product;
use BenSampo\Enum\Rules\EnumValue;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class ProductUnitChangeProductRequest extends FormRequest
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
            'product_id' => ['required', function (string $attribute, mixed $value, Closure $fail) {
                if (Product::where('id', $value)->where('company', $this->company)->doesntExist()) {
                    $fail('Product Tidak ditemukan');
                }
            }],
        ];
    }
}
