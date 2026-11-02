<?php

namespace App\Http\Requests\Api\Permission;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'name' => 'required|unique:roles,name'.$this->permission?->id,
            'parent_id' => 'nullable',
        ];
    }
}
