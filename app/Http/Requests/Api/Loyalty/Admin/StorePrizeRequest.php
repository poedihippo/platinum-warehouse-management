<?php

namespace App\Http\Requests\Api\Loyalty\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePrizeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'points_cost' => ['required', 'integer', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama hadiah wajib diisi.',
            'points_cost.required' => 'Nilai poin wajib diisi.',
            'points_cost.min' => 'Nilai poin tidak boleh negatif.',
            'stock.required' => 'Stok wajib diisi.',
            'stock.min' => 'Stok tidak boleh negatif.',
            'photo.image' => 'File harus berupa gambar.',
            'photo.max' => 'Ukuran gambar maksimal 5MB.',
        ];
    }
}
