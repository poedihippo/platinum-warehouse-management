<?php

namespace App\Listeners\ProductUnits;

use App\Events\ProductUnits\ProductUnitCreated;
use App\Models\Warehouse;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CreateStockProductUnit implements ShouldQueue
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
     * @param  \App\Events\ProductUnits\ProductUnitCreated  $event
     * @return void
     */
    public function handle(ProductUnitCreated $event)
    {
        $productUnit = $event->productUnit;

        Warehouse::select('id')->withTrashed()->get()
            ->each(function ($warehouse) use ($productUnit) {
                $productUnit->stockProductUnits()->create([
                    'warehouse_id' => $warehouse->id
                ]);
            });
    }
}
