<?php

namespace App\Listeners;

use App\Events\VerifiedRODetailEvent;
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
            $qr = QrCode::size(300)
                ->format('svg')
                ->generate('01h0f8j05z7r0sp42ynm0jf2bs');

            $receiveOrderDetail->stocks()->create([
                'product_unit_id' => $receiveOrderDetail->product_unit_id,
                'warehouse_id' => $receiveOrderDetail->receiveOrder->warehouse_id,
                'qr_code' => $qr,
            ]);
        }
    }
}
