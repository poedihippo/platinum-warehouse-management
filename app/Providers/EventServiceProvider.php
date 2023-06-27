<?php

namespace App\Providers;

use App\Events\ProductUnits\ProductUnitCreated;
use App\Events\Stocks\StockOpnameCreated;
use App\Events\Stocks\StockOpnameDetailCreated;
use App\Events\UnverifiedROEvent;
use App\Events\VerifiedROEvent;
use App\Listeners\CreateStockROListener;
use App\Listeners\DeleteStockROListener;
use App\Listeners\ProductUnits\CreateStockProductUnit;
use App\Listeners\Stocks\CreateStockOpnameDetail;
use App\Listeners\Stocks\CreateStockOpnameItems;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        VerifiedROEvent::class => [
            CreateStockROListener::class,
        ],

        UnverifiedROEvent::class => [
            DeleteStockROListener::class,
        ],

        ProductUnitCreated::class => [
            CreateStockProductUnit::class,
        ],

        StockOpnameCreated::class => [
            CreateStockOpnameDetail::class,
        ],

        StockOpnameDetailCreated::class => [
            CreateStockOpnameItems::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     *
     * @return bool
     */
    public function shouldDiscoverEvents()
    {
        return false;
    }
}
