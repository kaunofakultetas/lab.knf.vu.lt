<?php

// -----------------------------------------------------------
//  [*] EncryptCookies — encrypts the site's cookies
//
//  Laravel's cookie encryption: every cookie the app sets,
//  the session cookie and the CSRF token included, is
//  encrypted with APP_KEY, and a cookie that does not
//  decrypt is dropped. The one cookie left out is the one a
//  VU sign-out leaves, which SimpleSAMLphp sets outside
//  Laravel and so cannot encrypt (SamlAuth::recordSignOut).
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
// Laravel's middleware, leaving out the VU sign-out's
// cookie.
//
// Used by:
//   - app/Http/Kernel.php — the web group
// -----------------------------------------------------------

class EncryptCookies extends Middleware
{

    // The cookies left unencrypted
    protected $except = [
        SamlAuth::SIGNED_OUT_COOKIE,
    ];
}
