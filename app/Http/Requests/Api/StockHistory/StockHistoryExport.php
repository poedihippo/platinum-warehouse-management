<?php

namespace App\Http\Requests\Api\StockHistory;

use App\Enums\SalesOrderType;
use App\Rules\TenantedRule;
use BenSampo\Enum\Rules\EnumValue;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class StockHistoryExport extends FormRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $startDate = $this->start_date;
        if(!$startDate) $startDate = date('Y-m-d');

        $endDate = $this->end_date;
        if(!$endDate) $endDate = $startDate;

        $this->merge([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ];
    }
}
