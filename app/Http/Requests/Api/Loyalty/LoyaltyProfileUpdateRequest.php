<?php

namespace App\Http\Requests\Api\Loyalty;

use Illuminate\Foundation\Http\FormRequest;

class LoyaltyProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
