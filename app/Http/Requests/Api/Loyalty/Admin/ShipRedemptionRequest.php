<?php

namespace App\Http\Requests\Api\Loyalty\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ShipRedemptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tracking_number' => ['required', 'string', 'max:255'],
            'shipping_carrier' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'tracking_number.required' => 'Nomor resi wajib diisi.',
            'shipping_carrier.required' => 'Kurir pengiriman wajib diisi.',
        ];
    }
}
