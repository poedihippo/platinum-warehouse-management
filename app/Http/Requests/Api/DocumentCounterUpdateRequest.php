<?php

namespace App\Http\Requests\Api;

use App\Enums\CompanyEnum;
use App\Enums\DocumentType;
use BenSampo\Enum\Rules\EnumValue;
use Illuminate\Foundation\Http\FormRequest;

class DocumentCounterUpdateRequest extends FormRequest
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
