<?php

namespace App\Listeners\Stocks;

use App\Events\Stocks\StockOpnameCreated;
use App\Models\StockProductUnit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CreateStockOpnameDetail implements ShouldQueue
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
     * @param  \App\Events\Stocks\StockOpnameCreated  $event
     * @return void
     */
    public function handle(StockOpnameCreated $event)
    {
        $stockOpname = $event->stockOpname;

        StockProductUnit::select('id')
            ->where('warehouse_id', $stockOpname->warehouse_id)
            // ->withCount('stocks')
            ->get()?->each(function ($stockProductUnit) use ($stockOpname) {
                $stockOpname->details()->create([
                    'stock_product_unit_id' => $stockProductUnit->id,
                    'qty' => $stockProductUnit->stocks()->whereAvailableStock()->count() ?? 0,
                ]);
            });

        // Stock::select('product_unit_id')
        //     ->where('warehouse_id', $stockOpname->warehouse_id)
        //     ->groupBy('product_unit_id')
        //     ->get()?->each(function ($stock) use ($stockOpname) {
        //         $stockOpname->details()->create([
        //             'product_unit_id' => $stock->product_unit_id,
        //         ]);
        //     });
    }
}
