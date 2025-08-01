<?php

namespace App\Http\Requests\Api;

use App\Traits\Requests\RequestToBoolean;
use Illuminate\Foundation\Http\FormRequest;

class VerificationTempelRequest extends FormRequest
{
    use RequestToBoolean;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_tempel' => $this->is_tempel ? $this->toBoolean($this->is_tempel) : null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'is_tempel' => 'required|boolean',
            'ids' => 'required|array',
            'ids.*' => 'required|string',
        ];
    }
}
