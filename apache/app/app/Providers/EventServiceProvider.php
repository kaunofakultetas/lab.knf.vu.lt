<?php

// -----------------------------------------------------------
//  [*] EventServiceProvider — event listeners
//
//  Laravel's skeleton listener map: Laravel's own
//  verification mail on the Registered event. The app never
//  fires that event — RegisterController mails its own
//  verification link — so the listener never runs.
//
//  Used by:
//    - config/app.php — the providers list
// -----------------------------------------------------------


namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;








// -----------------------------------------------------------
// EventServiceProvider
// -----------------------------------------------------------
//
// The listener map below, with automatic event discovery
// switched off.
//
// Used by:
//   - config/app.php — the providers list
// -----------------------------------------------------------

class EventServiceProvider extends ServiceProvider
{

    // Events and the listeners they trigger: Laravel's
    // verification mail on Registered, an event nothing fires
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];






    // -----------------------------------------------------------
    // boot
    // -----------------------------------------------------------
    //
    // Registers nothing beyond the listener map.
    //
    // Used by:
    //   - Laravel, once every provider is registered
    // -----------------------------------------------------------

    public function boot()
    {
    }






    // -----------------------------------------------------------
    // shouldDiscoverEvents
    // -----------------------------------------------------------
    //
    // No: listeners are only the ones the map names.
    //
    // Used by:
    //   - Laravel, while it registers the listeners
    // -----------------------------------------------------------

    public function shouldDiscoverEvents()
    {
        return false;
    }
}
