<?php

// -----------------------------------------------------------
//  [*] AppServiceProvider — the app's own service provider
//
//  Laravel's skeleton provider for the app's own bindings
//  and boot-time setup: the app registers nothing here, and
//  sets one thing up, the markup of its page links. The
//  Validator import is unused.
//
//  Used by:
//    - config/app.php — the providers list
// -----------------------------------------------------------


namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Validator;








// -----------------------------------------------------------
// AppServiceProvider
// -----------------------------------------------------------
//
// register is empty, and boot picks Bootstrap's markup for
// the page links. The recaptchav3 rule the forms validate
// with comes from the RecaptchaV3 package's own provider,
// not from here.
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
    // Has the page links of every paged list drawn with
    // Bootstrap 5's markup, the styling the control panel
    // loads; Laravel's default markup is Tailwind's, which the
    // panel cannot draw — arrows as wide as the table, and the
    // links twice over.
    //
    // Used by:
    //   - Laravel, once every provider is registered
    // -----------------------------------------------------------

    public function boot()
    {
        Paginator::useBootstrapFive();
    }
}
