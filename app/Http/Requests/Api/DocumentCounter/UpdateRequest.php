<?php

namespace App\Http\Requests\Api\DocumentCounter;

use App\Enums\CompanyEnum;
use App\Enums\DocumentType;
use App\Rules\EnumValue;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * `next_sequence` is the number the next document must receive, so the
     * stored sequence is derived as `next_sequence - 1`.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'company' => ['required', new EnumValue(CompanyEnum::class)],
            'document_type' => ['required', new EnumValue(DocumentType::class)],
            'period' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'next_sequence' => ['required', 'integer', 'min:1', 'max:999999'],
        ];
    }
}
