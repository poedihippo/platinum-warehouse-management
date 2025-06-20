<?php

namespace App\Http\Requests\Api\Stock;

use App\Traits\Requests\RequestToBoolean;
use Illuminate\Foundation\Http\FormRequest;

class AddToStockRequest extends FormRequest
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
            'is_add' => $this->toBoolean($this->is_add ?? 1),
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
            'is_add' => 'required|boolean',
            'ids' => 'required|array',
            'ids.*' => 'string|exists:stocks,id',
        ];
    }
}
