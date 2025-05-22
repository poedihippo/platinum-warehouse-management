<?php

namespace App\Http\Requests\Api\Stock;

use Illuminate\Foundation\Http\FormRequest;

class SetToPrintingQueueRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'printer_id' => 'required|numeric|in:1,2',
            'stocks' => 'required|array',
            'stocks.*' => 'string|exists:stocks,id',
        ];
    }
}
