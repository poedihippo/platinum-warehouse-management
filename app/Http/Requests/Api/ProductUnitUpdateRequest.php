<?php

namespace App\Http\Requests\Api;

use App\Traits\Requests\RequestToBoolean;
use Illuminate\Foundation\Http\FormRequest;

class ProductUnitUpdateRequest extends FormRequest
{
    use RequestToBoolean;

    /**
     * Prepare inputs for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        $data = [
            'is_generate_qr' => $this->toBoolean($this->is_generate_qr ?? 1),
            'is_auto_tempel' => $this->toBoolean($this->is_auto_tempel ?? 1),
            'is_ppn' => $this->toBoolean($this->is_ppn ?? 0),
        ];

        if ($this->is_auto_stock) {
            $data['is_auto_stock'] = $this->toBoolean($this->is_auto_stock);
        }

        $this->merge($data);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'code' => 'required|unique:product_units,code,' . $this->product_unit?->id,
            'uom_id' => 'required|exists:uoms,id',
            'name' => 'required',
            'description' => 'required',
            'product_id' => 'required',
            'price' => 'required',
            'packaging_id' => 'nullable|exists:product_units,id',
            'is_generate_qr' => 'nullable|boolean',
            'is_auto_tempel' => 'nullable|boolean',
            'is_ppn' => 'nullable|boolean',
            'is_auto_stock' => 'nullable|boolean',
        ];
    }
}
