<?php

// -----------------------------------------------------------
//  [*] BroadcastServiceProvider — event broadcasting routes
//
//  Laravel's skeleton provider for broadcasting: it would
//  register the channel authorisation route and load
//  routes/channels.php. The app broadcasts nothing, and
//  config/app.php leaves this provider switched off, so
//  neither ever happens.
//
//  Used by:
//    - nothing at the moment — config/app.php lists it
//      switched off
// -----------------------------------------------------------


namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;








// -----------------------------------------------------------
// BroadcastServiceProvider
// -----------------------------------------------------------
//
// One hook, boot, which nothing calls while the provider is
// switched off.
//
// Used by:
//   - nothing at the moment
// -----------------------------------------------------------

class BroadcastServiceProvider extends ServiceProvider
{






    // -----------------------------------------------------------
    // boot
    // -----------------------------------------------------------
    //
    // Registers the broadcasting authorisation route and the
    // channels of routes/channels.php.
    //
    // Used by:
    //   - Laravel, if the provider were switched on
    // -----------------------------------------------------------

    public function boot()
    {
        Broadcast::routes();

        require base_path('routes/channels.php');
    }
}
