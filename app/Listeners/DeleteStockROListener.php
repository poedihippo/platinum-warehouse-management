<?php

namespace App\Listeners;

use App\Events\UnverifiedROEvent;
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
        $receiveOrder = $event->receiveOrder;
        foreach ($receiveOrder->details as $receiveOrderDetail) {
            $receiveOrderDetail->stocks?->each->forceDelete();
            Storage::deleteDirectory($receiveOrderDetail->id);
        }
    }
}
