<?php

// -----------------------------------------------------------
//  [*] AppServiceProvider — the app's own service provider
//
//  Laravel's skeleton provider for the app's own bindings
//  and boot-time setup: the app registers nothing here, and
//  sets two things up, the markup of its page links and the
//  page a session signed out for a changed password goes
//  to. The Validator import is unused.
//
//  Used by:
//    - config/app.php — the providers list
// -----------------------------------------------------------


namespace App\Providers;

use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Validator;








// -----------------------------------------------------------
// AppServiceProvider
// -----------------------------------------------------------
//
// register is empty, and boot picks Bootstrap's markup for
// the page links and where AuthenticateSession sends a
// session it ends. The recaptchav3 rule the forms validate
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
    // links twice over. And sends a session AuthenticateSession
    // signs out, its account's password changed since, to the
    // players' login page — it runs on the player pages only —
    // as Laravel 13 answers it with an empty 401 when told no
    // page.
    //
    // Used by:
    //   - Laravel, once every provider is registered
    // -----------------------------------------------------------

    public function boot()
    {
        Paginator::useBootstrapFive();

        AuthenticateSession::redirectUsing(fn (Request $request) => route('login_users'));
    }
}
