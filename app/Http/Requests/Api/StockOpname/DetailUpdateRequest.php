<?php

namespace App\Http\Requests\Api\StockOpname;

use Illuminate\Foundation\Http\FormRequest;

class DetailUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'description' => 'nullable',
        ];
    }
}
