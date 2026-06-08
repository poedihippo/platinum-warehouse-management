<?php

namespace App\Http\Requests\Api\Loyalty\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePrizeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'points_cost' => ['sometimes', 'required', 'integer', 'min:0'],
            'stock' => ['sometimes', 'required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'photo' => ['sometimes', 'nullable', 'image', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'points_cost.min' => 'Nilai poin tidak boleh negatif.',
            'stock.min' => 'Stok tidak boleh negatif.',
            'photo.image' => 'File harus berupa gambar.',
            'photo.max' => 'Ukuran gambar maksimal 5MB.',
        ];
    }
}
