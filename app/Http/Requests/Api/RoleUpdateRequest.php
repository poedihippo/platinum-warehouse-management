<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class RoleUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        $role = $this->route('role');

        return [
            'name' => 'required|unique:roles,name,' . $role->id,
            'permission_ids' => 'nullable|array',
            // 'permission_ids.*' => 'exists:permissions,id',
        ];
    }
}
