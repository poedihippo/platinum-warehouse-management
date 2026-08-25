<?php

namespace App\Http\Requests\Api;

use App\Models\Stock;
use App\Models\StockProductUnit;
use App\Rules\TenantedRule;
use Illuminate\Foundation\Http\FormRequest;

class TransferStockRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'stock_product_unit_id' => ['required', 'integer', 'exists:stock_product_units,id'],
            'to_warehouse_id' => ['required', new TenantedRule(\App\Models\Warehouse::class)],
            'stock_ids' => ['required', 'array', 'min:1'],
            'stock_ids.*' => ['required', 'string'],
            'qty' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $hasStockIds = ! empty($this->stock_ids);
            $hasSpuId = ! empty($this->stock_product_unit_id);

            if ($hasStockIds) {
                $this->validateQrFlow($validator);
            } elseif ($hasSpuId) {
                $this->validateNonQrFlow($validator);
            }
        });
    }

    private function validateQrFlow($validator): void
    {
        $fromSpu = StockProductUnit::with('productUnit')
            ->where('id', $this->stock_product_unit_id)
            ->first();

        if (! $fromSpu) {
            $validator->errors()->add('stock_product_unit_id', 'Stock product unit asal tidak ditemukan');

            return;
        }

        if ($fromSpu->productUnit->is_generate_qr) {
            // QR flow — ok
        } else {
            $validator->errors()->add('stock_product_unit_id', 'Product unit ini tidak menggunakan QR. Gunakan qty untuk transfer');

            return;
        }

        $toWarehouseId = $this->to_warehouse_id;
        if ($fromSpu->warehouse_id == $toWarehouseId) {
            $validator->errors()->add('to_warehouse_id', 'Warehouse tujuan harus berbeda dengan warehouse asal');

            return;
        }

        $toSpu = StockProductUnit::where('product_unit_id', $fromSpu->product_unit_id)
            ->where('warehouse_id', $toWarehouseId)
            ->first();

        if (! $toSpu) {
            $validator->errors()->add('to_warehouse_id', 'Stock product unit tujuan tidak ditemukan');

            return;
        }

        $validator->merge([
            'from_stock_product_unit_id' => $fromSpu->id,
            'to_stock_product_unit_id' => $toSpu->id,
            'product_unit_id' => $fromSpu->product_unit_id,
            'from_warehouse_id' => $fromSpu->warehouse_id,
        ]);

        $this->validateStockIds($validator, $fromSpu->id);
    }

    private function validateStockIds($validator, int $fromSpuId): void
    {
        $stocks = Stock::whereIn('id', $this->stock_ids)->get();
        $foundIds = $stocks->pluck('id')->toArray();
        $missingIds = array_diff($this->stock_ids, $foundIds);

        if ($missingIds !== []) {
            $validator->errors()->add('stock_ids', 'Stock tidak ditemukan: '.implode(', ', $missingIds));
        }

        foreach ($stocks as $stock) {
            if (! $stock->is_stock) {
                $validator->errors()->add('stock_ids', 'Stock "'.$stock->id.'" bukan stock aktif');

                return;
            }

            if (! is_null($stock->parent_id)) {
                $validator->errors()->add('stock_ids', 'Stock "'.$stock->id.'" adalah child. Scan QR parent-nya');

                return;
            }

            if ($stock->stock_product_unit_id != $fromSpuId) {
                $validator->errors()->add('stock_ids', 'Stock "'.$stock->id.'" tidak sesuai dengan product unit asal');

                return;
            }

            if ($stock->salesOrderItems()->whereNotReturned()->exists()) {
                $validator->errors()->add('stock_ids', 'Stock "'.$stock->id.'" sudah masuk di Sales Order');

                return;
            }
        }
    }

    private function validateNonQrFlow($validator): void
    {
        if (empty($this->qty)) {
            $validator->errors()->add('qty', 'Qty wajib diisi untuk transfer non-QR');

            return;
        }

        $fromSpu = StockProductUnit::with('productUnit')
            ->where('id', $this->stock_product_unit_id)
            ->first();

        if (! $fromSpu) {
            $validator->errors()->add('stock_product_unit_id', 'Stock product unit tidak ditemukan');

            return;
        }

        if ($fromSpu->productUnit->is_generate_qr) {
            $validator->errors()->add('stock_product_unit_id', 'Product unit ini menggunakan QR. Gunakan stock_ids untuk transfer');

            return;
        }

        if ($fromSpu->qty < $this->qty) {
            $validator->errors()->add('qty', 'Qty tidak mencukupi. Tersedia: '.$fromSpu->qty);

            return;
        }

        $toWarehouseId = $this->to_warehouse_id;
        if ($fromSpu->warehouse_id == $toWarehouseId) {
            $validator->errors()->add('to_warehouse_id', 'Warehouse tujuan harus berbeda dengan warehouse asal');

            return;
        }

        $productUnitId = $fromSpu->product_unit_id;
        $toSpu = StockProductUnit::where('product_unit_id', $productUnitId)
            ->where('warehouse_id', $toWarehouseId)
            ->first();

        if (! $toSpu) {
            $validator->errors()->add('to_warehouse_id', 'Stock product unit tujuan tidak ditemukan');

            return;
        }

        $validator->merge([
            'from_stock_product_unit_id' => $fromSpu->id,
            'to_stock_product_unit_id' => $toSpu->id,
            'product_unit_id' => $productUnitId,
            'from_warehouse_id' => $fromSpu->warehouse_id,
        ]);
    }
}
