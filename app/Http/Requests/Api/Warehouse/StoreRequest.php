<?php

namespace App\Http\Requests\Api\Warehouse;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'code' => ['required', 'unique:warehouses,code'],
            'name' => ['required', 'string'],
            'company_name' => ['required', 'string'],
            'is_for_marketplace' => ['sometimes', 'boolean'],
        ];
    }
}
