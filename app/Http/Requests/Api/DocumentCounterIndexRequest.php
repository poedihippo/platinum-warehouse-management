<?php

namespace App\Http\Requests\Api;

use App\Enums\CompanyEnum;
use App\Enums\DocumentType;
use BenSampo\Enum\Rules\EnumValue;
use Illuminate\Foundation\Http\FormRequest;

class DocumentCounterIndexRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * All parameters are optional: without them the whole scope matrix of the
     * running period is returned.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'period' => ['nullable', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'company' => ['nullable', new EnumValue(CompanyEnum::class)],
            'document_type' => ['nullable', new EnumValue(DocumentType::class)],
        ];
    }
}
