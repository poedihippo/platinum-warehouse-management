<?php

namespace App\Http\Requests\Api\Stock;

use Illuminate\Foundation\Http\FormRequest;

class SetToPrintingQueueRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'expired_date' => 'nullable|date',
            'printer_id' => 'required|numeric|in:1,2',
            'stocks' => 'required|array',
            'stocks.*' => 'string|exists:stocks,id',
        ];
    }
}
