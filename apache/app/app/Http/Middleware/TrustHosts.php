<?php

// -----------------------------------------------------------
//  [*] TrustHosts — the Host header check, switched off
//
//  Laravel's host check: switched on, it would answer only
//  requests for the APP_URL host and its subdomains. The
//  kernel leaves it switched off, so any Host header is
//  answered.
//
//  Used by:
//    - nothing at the moment — app/Http/Kernel.php lists it
//      switched off
// -----------------------------------------------------------


namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts as Middleware;








// -----------------------------------------------------------
// TrustHosts
// -----------------------------------------------------------
//
// One method, hosts, which nothing calls while the
// middleware is switched off.
//
// Used by:
//   - nothing at the moment
// -----------------------------------------------------------

class TrustHosts extends Middleware
{






    // -----------------------------------------------------------
    // hosts
    // -----------------------------------------------------------
    //
    // The trusted host patterns: the APP_URL host and every
    // subdomain of it.
    //
    // Used by:
    //   - Laravel's TrustHosts middleware, if it were switched
    //     on
    // -----------------------------------------------------------

    public function hosts()
    {
        return [
            $this->allSubdomainsOfApplicationUrl(),
        ];
    }
}
