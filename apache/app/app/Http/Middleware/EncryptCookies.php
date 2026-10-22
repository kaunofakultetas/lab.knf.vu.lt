<?php

// -----------------------------------------------------------
//  [*] EncryptCookies — encrypts the site's cookies
//
//  Laravel's cookie encryption with nothing left out: every
//  cookie the app sets, the session cookie and the CSRF
//  token included, is encrypted with APP_KEY, and a cookie
//  that does not decrypt is dropped.
//
//  Used by:
//    - app/Http/Kernel.php — the web group
// -----------------------------------------------------------


namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;








// -----------------------------------------------------------
// EncryptCookies
// -----------------------------------------------------------
//
// Laravel's middleware with an empty except list.
//
// Used by:
//   - app/Http/Kernel.php — the web group
// -----------------------------------------------------------

class EncryptCookies extends Middleware
{

    // The cookies left unencrypted — none
    protected $except = [
    ];
}
