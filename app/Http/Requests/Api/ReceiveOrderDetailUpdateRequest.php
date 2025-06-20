<?php

namespace App\Http\Requests\Api;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class ReceiveOrderDetailUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        $receiveOrderDetail = $this->receiveOrder?->details()->where('id', $this->receiveOrderDetail)->firstOrFail();

        return [
            'adjust_qty' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, Closure $fail) use ($receiveOrderDetail) {
                    if (($receiveOrderDetail->qty % $value) > 0) {
                        $fail('Pembagian quantity tidak sesuai');
                    }
                },
            ],
            // 'uom_id' => 'required|exists:uoms,id',
            // 'is_package' => 'nullable|boolean'
        ];
    }

    // /**
    //  * Prepare inputs for validation.
    //  *
    //  * @return void
    //  */
    // protected function prepareForValidation()
    // {
    //     $this->merge([
    //         'is_package' => $this->toBoolean($this->is_package),
    //     ]);
    // }

    // /**
    //  * Convert to boolean
    //  *
    //  * @param $booleable
    //  * @return boolean
    //  */
    // private function toBoolean($booleable)
    // {
    //     return filter_var($booleable, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    // }
}
