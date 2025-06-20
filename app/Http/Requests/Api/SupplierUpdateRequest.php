<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SupplierUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'code' => 'required|unique:suppliers,code,' . $this->supplier->id,
            'name' => 'required',
            'email' => 'required',
            'phone' => 'required',
            'description' => 'nullable',
            'address' => 'required',
        ];
    }
}
