<?php

namespace App\Http\Requests\Api\Loyalty;

use Illuminate\Foundation\Http\FormRequest;

class LoyaltyClaimStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Note: the `image` validation rule relies on getimagesize(), which
     * does not recognise HEIC. The spec requires HEIC support, so we
     * validate by mime/extension allow-list + size instead of `image`.
     * 5 MB == 5120 KB.
     */
    public function rules(): array
    {
        return [
            'invoice_number' => ['required', 'string', 'max:100'],
            'invoice_photo' => ['required', 'file', 'mimes:jpg,jpeg,png,heic', 'max:5120'],
            'product_photos' => ['required', 'array', 'min:1', 'max:6'],
            'product_photos.*' => ['required', 'file', 'mimes:jpg,jpeg,png,heic', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'invoice_number.required' => 'Nomor invoice wajib diisi.',
            'invoice_number.max' => 'Nomor invoice maksimal 100 karakter.',
            'invoice_photo.required' => 'Foto invoice wajib diunggah.',
            'invoice_photo.mimes' => 'Foto invoice harus berformat JPG, PNG, atau HEIC.',
            'invoice_photo.max' => 'Ukuran foto invoice maksimal 5 MB.',
            'product_photos.required' => 'Minimal satu foto produk wajib diunggah.',
            'product_photos.min' => 'Minimal satu foto produk wajib diunggah.',
            'product_photos.max' => 'Maksimal 6 foto produk.',
            'product_photos.*.mimes' => 'Foto produk harus berformat JPG, PNG, atau HEIC.',
            'product_photos.*.max' => 'Ukuran setiap foto produk maksimal 5 MB.',
        ];
    }
}
