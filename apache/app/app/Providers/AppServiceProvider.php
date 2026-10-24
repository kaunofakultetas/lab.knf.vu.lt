<?php

// -----------------------------------------------------------
//  [*] AppServiceProvider — the app's own service provider
//
//  Laravel's skeleton provider for the app's own bindings
//  and boot-time setup, left empty: the app registers
//  nothing here. The Validator import is unused.
//
//  Used by:
//    - config/app.php — the providers list
// -----------------------------------------------------------


namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Validator;








// -----------------------------------------------------------
// AppServiceProvider
// -----------------------------------------------------------
//
// Both hooks are empty. The recaptchav3 rule the forms
// validate with comes from the RecaptchaV3 package's own
// provider, not from here.
//
// Used by:
//   - config/app.php — the providers list
// -----------------------------------------------------------

class AppServiceProvider extends ServiceProvider
{






    // -----------------------------------------------------------
    // register
    // -----------------------------------------------------------
    //
    // Binds nothing into the container.
    //
    // Used by:
    //   - Laravel, while it registers the providers at boot
    // -----------------------------------------------------------

    public function register()
    {
    }






    // -----------------------------------------------------------
    // boot
    // -----------------------------------------------------------
    //
    // Sets nothing up.
    //
    // Known bug LAB-65 (low): the paginators keep Laravel's
    // Tailwind markup, which the Bootstrap-styled panel cannot
    // draw. Fix: Paginator::useBootstrapFive().
    //
    // Used by:
    //   - Laravel, once every provider is registered
    // -----------------------------------------------------------

    public function boot()
    {
    }
}
