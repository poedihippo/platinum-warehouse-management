<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveOrderDetailStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            // 'supplier_id' => 'required|exists:suppliers,id',
            'name' => 'required',
            'description' => 'nullable',
            'receive_datetime' => 'required|date_format:Y-m-d H:i:s',
            'file' => 'required|mimes:xml',
        ];
    }
}
