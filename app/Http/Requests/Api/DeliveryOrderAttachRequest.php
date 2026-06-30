<?php

namespace App\Http\Requests\Api;

use App\Models\DeliveryOrderDetail;
use App\Models\SalesOrderDetail;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class DeliveryOrderAttachRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    // {
    //     return [
    //         'sales_order_detail_ids' => 'required|array',
    //         'sales_order_detail_ids.*' => ['integer', 'exists:sales_order_details,id', function ($attribute, $value, Closure $fail) {
    //             $salesOrderDetail = SalesOrderDetail::find($value);
    //             if ($salesOrderDetail?->deliveryOrderDetail) $fail('Sales order data has been used in another delivery order');
    //         }],
    //     ];
    // }

    {
        return [
            'details'                         => 'required|array|min:1',
            'details.*.sales_order_detail_id' => [
                'required',
                'integer',
                'exists:sales_order_details,id',
                function ($attribute, $value, Closure $fail) {
                    $salesOrderDetail = SalesOrderDetail::find($value);
                    if (!$salesOrderDetail) {
                        $fail('Sales order detail tidak ditemukan.');
                        return;
                    }

                    // Hitung sisa qty yang masih bisa di-DO-kan
                    // Kecualikan DO saat ini jika sedang update
                    $doId = $this->route('id'); // ambil dari route parameter
                    $alreadyScheduled = DeliveryOrderDetail::where('sales_order_detail_id', $value)
                        ->when($doId, fn($q) => $q->where('delivery_order_id', '!=', $doId))
                        ->sum('qty');

                    $remaining = $salesOrderDetail->qty - $alreadyScheduled;

                    if ($remaining <= 0) {
                        $fail('Sales order detail ini sudah penuh terjadwal di delivery order lain.');
                    }
                }
            ],
            'details.*.qty' => [
                'required',
                'integer',
                'min:1',
                function ($attribute, $value, Closure $fail) {
                    // Ambil index dari attribute, contoh: details.0.qty
                    $index = explode('.', $attribute)[1];
                    $salesOrderDetailId = $this->input("details.{$index}.sales_order_detail_id");

                    if (!$salesOrderDetailId) return;

                    $salesOrderDetail = SalesOrderDetail::find($salesOrderDetailId);
                    if (!$salesOrderDetail) return;

                    $doId = $this->route('id');
                    $alreadyScheduled = DeliveryOrderDetail::where('sales_order_detail_id', $salesOrderDetailId)
                        ->when($doId, fn($q) => $q->where('delivery_order_id', '!=', $doId))
                        ->sum('qty');

                    $remaining = $salesOrderDetail->qty - $alreadyScheduled;

                    if ($value > $remaining) {
                        $fail("Qty melebihi sisa yang tersedia. Sisa: {$remaining}, diminta: {$value}.");
                    }
                }
            ],
        ];
    }

    public function messages()
    {
        return [
            'details.required'                         => 'Details tidak boleh kosong.',
            'details.*.sales_order_detail_id.required' => 'Sales order detail ID wajib diisi.',
            'details.*.sales_order_detail_id.exists'   => 'Sales order detail tidak ditemukan.',
            'details.*.qty.required'                   => 'Qty wajib diisi.',
            'details.*.qty.min'                        => 'Qty minimal 1.',
        ];
    }
}
