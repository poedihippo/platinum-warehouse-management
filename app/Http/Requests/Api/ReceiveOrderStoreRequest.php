<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveOrderStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'receive_datetime' => 'required|date_format:Y-m-d H:i:s',
            'file' => 'required|mimes:xml',
        ];
    }
}
