<?php

namespace App\Listeners\Stocks;

use App\Events\Stocks\StockOpnameDetailCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CreateStockOpnameItems implements ShouldQueue
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
     * @param  \App\Events\Stocks\StockOpnameDetailCreated  $event
     * @return void
     */
    public function handle(StockOpnameDetailCreated $event)
    {
        $stockOpnameDetail = $event->stockOpnameDetail;

        $stockOpnameDetail->stockProductUnit?->stocks()->whereAvailableStock()->get()->each(function ($stock) use ($stockOpnameDetail) {
            $stockOpnameDetail->stockOpnameItems()->create([
                'stock_id' => $stock->id,
                'is_scanned' => 0,
            ]);
        });
    }
}
