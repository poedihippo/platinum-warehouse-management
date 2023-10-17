<?php

namespace App\Listeners;

use App\Events\UnverifiedROEvent;
use App\Models\StockProductUnit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;

class DeleteStockROListener implements ShouldQueue
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
     * @param  \App\Events\UnverifiedROEvent  $event
     * @return void
     */
    public function handle(UnverifiedROEvent $event)
    {
        $user = $event->user;
        $receiveOrder = $event->receiveOrder;

        foreach ($receiveOrder->details as $receiveOrderDetail) {
            $receiveOrderDetail->stocks?->each->forceDelete();

            $qty = $receiveOrderDetail->adjust_qty > 0 ? $receiveOrderDetail->adjust_qty : $receiveOrderDetail->qty;
            $stockProductUnit = StockProductUnit::select('id')->where('warehouse_id', $receiveOrder->warehouse_id)
                ->where('product_unit_id', $receiveOrderDetail->product_unit_id)
                ->first();

            if ($stockProductUnit) {
                if ($stockProductUnit->productUnit->is_generate_qr) {
                    $stockProductUnit->decrement('qty', $qty);
                }

                // create history
                $receiveOrderDetail->histories()->create([
                    'user_id' => $user->id,
                    'stock_product_unit_id' => $stockProductUnit->id,
                    'value' => $qty,
                    'is_increment' => 0,
                    'description' => $receiveOrder->invoice_no,
                    'ip' => request()->ip(),
                    'agent' => request()->header('user-agent'),
                ]);
            }

            Storage::deleteDirectory($receiveOrderDetail->id);
        }
    }
}
