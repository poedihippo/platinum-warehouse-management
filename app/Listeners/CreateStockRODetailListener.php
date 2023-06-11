<?php

namespace App\Listeners;

use App\Events\VerifiedRODetailEvent;
use App\Models\Stock;
use App\Models\StockProductUnit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class CreateStockRODetailListener implements ShouldQueue
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  \App\Events\VerifiedRODetailEvent  $event
     * @return void
     */
    public function handle(VerifiedRODetailEvent $event)
    {
        $receiveOrderDetail = $event->receiveOrderDetail->load('receiveOrder');
        $folder = 'qrcode/';

        for ($i = 0; $i < $receiveOrderDetail->adjust_qty ?? 0; $i++) {
            $stockProductUnit = StockProductUnit::where('warehouse_id', $receiveOrderDetail->receiveOrder->warehouse_id)
                ->where('product_unit_id', $receiveOrderDetail->product_unit_id)
                ->first();

            $stock = $stockProductUnit->stocks()->create([
                'receive_order_id' => $receiveOrderDetail->receive_order_id,
                'receive_order_detail_id' => $receiveOrderDetail->id,
            ]);

            // $stock = Stock::create([
            //     'receive_order_id' => $receiveOrderDetail->receive_order_id,
            //     'receive_order_detail_id' => $receiveOrderDetail->id,
            //     'product_unit_id' => $receiveOrderDetail->product_unit_id,
            //     'warehouse_id' => $receiveOrderDetail->receiveOrder->warehouse_id,
            // ]);

            $logo = public_path('images/logo-platinum.png');

            $data = QrCode::size(350)
                ->format('png')
                ->merge($logo, absolute: true)
                ->generate($stock->id);

            $fileName = $receiveOrderDetail->id . '/' . $stock->id . '.png';
            $fullPath = $folder .  $fileName;
            Storage::put($fullPath, $data);

            $stock->update(['qr_code' => $fullPath]);
        }
    }
}
