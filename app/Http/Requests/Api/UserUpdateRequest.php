<?php

namespace App\Http\Requests\Api;

use App\Enums\UserType;
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
            'name' => 'required',
            'email' => 'nullable|email|unique:users,email,' . $this->user->id,
            'password' => 'nullable',
            'phone' => 'nullable|unique:users,phone',
            'address' => 'nullable',
            'tax_address' => 'nullable',
            'provider_id' => 'nullable|unique:users,provider_id',
            'provider_name' => 'nullable',
            'city' => 'nullable',
            'province' => 'nullable',
            'zip_code' => 'nullable',
            'country' => 'nullable',
            'contact_person' => 'nullable',
            'web_page' => 'nullable',
            'type' => ['nullable', new EnumValue(UserType::class, false)],
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'exists:roles,id',
            'warehouse_ids' => 'nullable|array',
            'warehouse_ids.*' => new TenantedRule(),
        ];
    }
}
