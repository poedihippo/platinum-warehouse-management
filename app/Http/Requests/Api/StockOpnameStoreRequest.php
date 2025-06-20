<?php

namespace App\Http\Requests\Api;

use App\Rules\TenantedRule;
use Illuminate\Foundation\Http\FormRequest;

class StockOpnameStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'warehouse_id' => ['required', new TenantedRule()],
            'description' => 'required',
        ];
    }
}
