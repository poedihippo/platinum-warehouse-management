<?php

namespace App\Providers;

use App\Events\UnverifiedRODetailEvent;
use App\Events\VerifiedRODetailEvent;
use App\Listeners\CreateStockRODetailListener;
use App\Listeners\DeleteStockRODetailListener;
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

        VerifiedRODetailEvent::class => [
            CreateStockRODetailListener::class,
        ],

        UnverifiedRODetailEvent::class => [
            DeleteStockRODetailListener::class,
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
