<?php

namespace App\Http\Requests\Api;

use App\Enums\CompanyEnum;
use App\Enums\UserType;
use App\Rules\PhoneRule;
use App\Rules\StringRule;
use App\Rules\TenantedRule;
use BenSampo\Enum\Rules\EnumValue;
use Illuminate\Foundation\Http\FormRequest;

class UserUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'name' => ['sometimes', 'required', new StringRule()],
            'email' => ['sometimes', 'email', 'unique:users,email,' . $this->user->id, 'max:255'],
            'code' => ['sometimes', 'nullable', 'required_if:type,' . UserType::Reseller],
            'password' => ['sometimes', 'required_unless:type,' . UserType::Reseller],
            'type' => ['sometimes', new EnumValue(UserType::class, false)],
            'company' => ['sometimes', 'nullable', new EnumValue(CompanyEnum::class, false)],
            'phone' => ['sometimes', 'nullable', new PhoneRule(100)],
            'address' => ['sometimes', 'nullable', new StringRule(1000)],
            'tax_address' => ['sometimes', 'nullable', new StringRule()],
            'provider_id' => ['sometimes', 'nullable', 'unique:users,provider_id,' . $this->user->id],
            'provider_name' => ['sometimes', 'nullable', new StringRule()],
            'city' => ['sometimes', 'nullable', new StringRule()],
            'province' => ['sometimes', 'nullable', new StringRule()],
            'zip_code' => ['sometimes', 'nullable', new StringRule(7)],
            'country' => ['sometimes', 'nullable', new StringRule()],
            'contact_person' => ['sometimes', 'nullable', new StringRule()],
            'web_page' => ['sometimes', 'nullable', new StringRule(300)],

            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['exists:roles,id'],
            'warehouse_ids' => ['nullable', 'array'],
            'warehouse_ids.*' => new TenantedRule(),
        ];
    }
}
