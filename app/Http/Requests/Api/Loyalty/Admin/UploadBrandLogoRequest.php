<?php

namespace App\Http\Requests\Api\Loyalty\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UploadBrandLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'logo' => ['nullable', 'image', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'logo.image' => 'File harus berupa gambar.',
            'logo.max' => 'Ukuran gambar maksimal 5MB.',
        ];
    }
}
