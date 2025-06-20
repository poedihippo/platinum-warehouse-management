<?php

namespace App\Http\Requests\Api\Stock;

use Illuminate\Foundation\Http\FormRequest;

class VerifyRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'stocks' => 'required|array',
            'stocks.*' => 'string|exists:stocks,id',
        ];
    }
}
