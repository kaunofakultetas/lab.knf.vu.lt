<?php

// -----------------------------------------------------------
//  [*] EventStatus — the event window check, switched off
//
//  Meant to keep players out of the lab outside the event
//  time range of the settings (cyber_settings, event_date):
//  no sign-in before the start, a sign-out after the end.
//  The check is switched off — every request passes — so
//  the lab is open at any time.
//
//  Used by:
//    - app/Http/Kernel.php — the event.status alias
//    - routes/web.php — POST /login and the player pages
//      under /user but the vote, feedback and SSO sign-out
//      routes
// -----------------------------------------------------------


namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\Settings;








// -----------------------------------------------------------
// EventStatus
// -----------------------------------------------------------
//
//   handle — lets every request through
//   getIp  — the visitor's address, from the client headers
//
// Used by:
//   - app/Http/Kernel.php — the event.status alias
// -----------------------------------------------------------

class EventStatus
{






    // -----------------------------------------------------------
    // handle
    // -----------------------------------------------------------
    //
    // Passes every request on.
    //
    // Used by:
    //   - the event.status alias — the routes above
    // -----------------------------------------------------------

    public function handle($request, Closure $next)
    {
        // The event window check is switched off: every request
        // passes
        return $next($request);
    }






    // -----------------------------------------------------------
    // getIp
    // -----------------------------------------------------------
    //
    // The visitor's address: the first public address in the
    // client headers, the proxy headers before REMOTE_ADDR,
    // skipping private and reserved ranges — or Laravel's
    // request()->ip() when no header holds one. Any client can
    // set those headers, so the answer is only a hint.
    //
    // Used by:
    //   - nothing at the moment — its one caller went with the
    //     switched-off check
    // -----------------------------------------------------------

    public static function getIp()
    {
        foreach (array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ) as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return request()->ip();
    }
}
