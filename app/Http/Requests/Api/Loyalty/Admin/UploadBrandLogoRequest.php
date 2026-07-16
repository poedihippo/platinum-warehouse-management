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
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'logo.image' => 'File harus berupa gambar.',
            'logo.mimes' => 'Format gambar harus PNG, JPG, atau WebP.',
            'logo.max' => 'Ukuran gambar maksimal 5MB.',
        ];
    }
}
