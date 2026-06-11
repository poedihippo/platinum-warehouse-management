<?php

namespace App\Http\Requests\Api\Loyalty;

use Illuminate\Foundation\Http\FormRequest;

class RedemptionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prize_id' => ['required', 'string', 'exists:prizes,id'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_phone' => ['required', 'string', 'max:50'],
            'recipient_address' => ['required', 'string', 'max:1000'],
            'recipient_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'prize_id.required' => 'Hadiah wajib dipilih.',
            'prize_id.exists' => 'Hadiah tidak ditemukan.',
            'recipient_name.required' => 'Nama penerima wajib diisi.',
            'recipient_phone.required' => 'Nomor telepon penerima wajib diisi.',
            'recipient_address.required' => 'Alamat penerima wajib diisi.',
        ];
    }
}
