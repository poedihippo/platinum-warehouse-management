<?php

namespace App\Http\Requests\Api;

use App\Enums\SalesOrderStatus;
use BenSampo\Enum\Rules\EnumValue;
use Illuminate\Foundation\Http\FormRequest;

class SalesOrderStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return user()->tokenCan('sales_order_create');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'user_id' => 'required',
            'reseller_id' => 'required',
            'transaction_date' => 'required|date_format:Y-m-d H:i:s',
            'status' => ['required', new EnumValue(SalesOrderStatus::class, false)],
        ];
    }
}
