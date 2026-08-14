<?php

namespace App\Http\Requests\Api\Loyalty\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLineItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.required' => 'Kuantitas wajib diisi.',
            'quantity.min' => 'Kuantitas minimal 1.',
        ];
    }
}
