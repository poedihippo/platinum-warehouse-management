<?php

namespace App\Imports;

use App\Jobs\GenerateStockQrcode;
use App\Models\ProductUnit;
use App\Models\StockProductUnit;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class StockImport implements ToModel, WithHeadingRow
{
    // use Queueable;

    public function __construct(public int $warehouse_id)
    {
    }
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        $qty = isset($row['stock']) && is_numeric($row['stock']) && $row['stock'] > 0 ? (int) $row['stock'] : 0;
        $qty = round($qty);
        $productUnit = ProductUnit::where('code', trim($row['code']))->first();
        if ($productUnit) {
            $folder = 'qrcode/';

            $stockProductUnit = StockProductUnit::where('warehouse_id', $this->warehouse_id)
                ->where('product_unit_id', $productUnit->id)
                ->first();

            if ($stockProductUnit && $qty > 0) {
                if($productUnit->is_generate_qr){
                    GenerateStockQrcode::dispatch($stockProductUnit, $qty, $folder);
                } else {
                    $stockProductUnit->increment('qty', $qty);
                }

                // for ($i = 0; $i < $qty ?? 0; $i++) {
                //     $stock = $stockProductUnit->stocks()->create([
                //         // 'receive_order_id' => $receiveOrderDetail->receive_order_id,
                //         // 'receive_order_detail_id' => $receiveOrderDetail->id,
                //     ]);

                //     // $logo = public_path('images/logo-platinum.png');

                //     $data = QrCode::size(350)
                //         ->format('png')
                //         // ->merge($logo, absolute: true)
                //         ->generate($stock->id);

                //         // $fileName = $receiveOrderDetail->id . '/' . $stock->id . '.png';
                //     $fileName = 'import/'.$stock->id . '.png';
                //     $fullPath = $folder .  $fileName;
                //     Storage::put($fullPath, $data);

                //     $stock->update(['qr_code' => $fullPath]);
                // }

                // create history
                // $receiveOrderDetail->histories()->create([
                //     'user_id' => $user->id,
                //     'stock_product_unit_id' => $stockProductUnit->id,
                //     'value' => $qty,
                //     'is_increment' => 1,
                //     'description' => $receiveOrder->invoice_no,
                //     'ip' => request()->ip(),
                //     'agent' => request()->header('user-agent'),
                // ]);
            }
        }
    }

    // public function chunkSize(): int
    // {
    //     return 50;
    // }
}
