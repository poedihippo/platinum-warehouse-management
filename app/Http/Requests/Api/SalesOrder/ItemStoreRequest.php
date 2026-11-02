<?php

namespace App\Http\Requests\Api\SalesOrder;

use Illuminate\Foundation\Http\FormRequest;

class ItemStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'stock_id' => 'required',
            // 'stock_id' => 'required|exists:stocks,id',
        ];
    }
}
