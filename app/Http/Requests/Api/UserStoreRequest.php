<?php

namespace App\Http\Requests\Api;

use App\Enums\CompanyEnum;
use App\Enums\UserType;
use App\Rules\PhoneRule;
use App\Rules\StringRule;
use App\Rules\TenantedRule;
use BenSampo\Enum\Rules\EnumValue;
use Illuminate\Foundation\Http\FormRequest;

class UserStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'name' => ['required', new StringRule()],
            'email' => ['nullable', 'email', 'unique:users,email', 'max:255'],
            'code' => ['nullable', 'required_if:type,' . UserType::Reseller],
            'password' => ['nullable', 'required_unless:type,' . UserType::Reseller],
            'type' => ['nullable', new EnumValue(UserType::class, false)],
            'company' => ['nullable', new EnumValue(CompanyEnum::class, false)],
            'phone' => ['nullable', new PhoneRule(100)],
            'address' => ['nullable', new StringRule(1000)],
            'tax_address' => ['nullable', new StringRule()],
            'provider_id' => ['nullable', 'unique:users,provider_id'],
            'provider_name' => ['nullable', new StringRule()],
            'city' => ['nullable', new StringRule()],
            'province' => ['nullable', new StringRule()],
            'zip_code' => ['nullable', new StringRule(7)],
            'country' => ['nullable', new StringRule()],
            'contact_person' => ['nullable', new StringRule()],
            'web_page' => ['nullable', new StringRule(300)],

            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['exists:roles,id'],
            'warehouse_ids' => ['nullable', 'array'],
            'warehouse_ids.*' => new TenantedRule(),
        ];
    }
}
