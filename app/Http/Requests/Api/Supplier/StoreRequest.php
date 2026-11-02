<?php

namespace App\Http\Requests\Api\Supplier;

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
            'code' => 'required|unique:suppliers,code',
            'name' => 'required',
            'email' => 'required',
            'phone' => 'required',
            'description' => 'nullable',
            'address' => 'required',
        ];
    }
}
