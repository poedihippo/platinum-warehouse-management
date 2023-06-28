<?php

namespace App\Http\Requests\Api;

use App\Enums\UserType;
use BenSampo\Enum\Rules\EnumValue;
use Illuminate\Foundation\Http\FormRequest;

class UserUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->tokenCan('user_update');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'name' => 'required',
            'email' => 'required|email|unique:users,email,' . $this->user->id,
            'password' => 'nullable',
            'phone' => 'required',
            'address' => 'nullable',
            'tax_address' => 'nullable',
            'provider_id' => 'nullable|unique:users,provider_id',
            'provider_name' => 'nullable',
            'city' => 'nullable',
            'province' => 'nullable',
            'zip_code' => 'nullable',
            'country' => 'nullable',
            'phone' => 'nullable|unique:users,phone',
            'contact_person' => 'nullable',
            'web_page' => 'nullable',
            'type' => ['nullable', new EnumValue(UserType::class, false)],
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'exists:roles,id',
        ];
    }
}
