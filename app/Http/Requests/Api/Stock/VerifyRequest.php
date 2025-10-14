<?php

namespace App\Http\Requests\Api\Stock;

use App\Traits\Requests\RequestToBoolean;
use Illuminate\Foundation\Http\FormRequest;

class VerifyRequest extends FormRequest
{
    use RequestToBoolean;

    protected function prepareForValidation()
    {
        $this->merge([
            'is_preview' => $this->toBoolean($this->is_preview ?? 0),
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
            'is_preview' => ['nullable', 'boolean'],
            'stocks' => ['required', 'array'],
            'stocks.*' => ['string', 'exists:stocks,id'],
        ];
    }
}
