<?php

namespace App\Listeners;

use App\Events\VerifiedRODetailEvent;
use App\Models\Stock;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class CreateStockRODetailListener
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
        for ($i = 0; $i < $receiveOrderDetail->adjust_qty ?? 0; $i++) {

            $stock = Stock::create([
                'receive_order_id' => $receiveOrderDetail->receive_order_id,
                'receive_order_detail_id' => $receiveOrderDetail->id,
                'product_unit_id' => $receiveOrderDetail->product_unit_id,
                'warehouse_id' => $receiveOrderDetail->receiveOrder->warehouse_id,
            ]);

            $qr = QrCode::size(300)
                ->format('svg')
                ->generate($stock->id);

            $stock->update(['qr_code' => $qr]);
        }
    }
}
