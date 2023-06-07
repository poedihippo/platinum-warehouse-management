<?php

namespace App\Listeners;

use App\Events\UnverifiedRODetailEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;

class DeleteStockRODetailListener implements ShouldQueue
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
     * @param  \App\Events\UnverifiedRODetailEvent  $event
     * @return void
     */
    public function handle(UnverifiedRODetailEvent $event)
    {
        $receiveOrderDetail = $event->receiveOrderDetail;

        $receiveOrderDetail->stocks?->each->delete();
        Storage::deleteDirectory($receiveOrderDetail->id);
    }
}
