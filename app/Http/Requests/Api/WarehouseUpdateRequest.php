<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class WarehouseUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'code' => ['sometimes', 'required', 'unique:warehouses,code,' . $this->warehouse],
            'name' => ['sometimes', 'required', 'string'],
            'company_name' => ['sometimes', 'required', 'string'],
        ];
    }
}
