<?php

namespace App\Http\Requests\Api\ReceiveOrder;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'name' => 'required|unique:uoms,name,'.$this->uom?->id,
        ];
    }
}
