<?php

namespace App\Http\Requests\Api\Stock;

use App\Traits\Requests\RequestToBoolean;
use Illuminate\Foundation\Http\FormRequest;

class ReturnRequest extends FormRequest
{
    use RequestToBoolean;

    /**
     * Prepare inputs for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'is_delete' => $this->toBoolean($this->is_delete ?? 0),
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
            'is_delete' => ['nullable', 'boolean'],
            'ids' => ['required', 'array', 'max:100'],
            'ids.*' => ['string', 'exists:stocks,id'],
        ];
    }
}
