<?php

// -----------------------------------------------------------
//  [*] TrustProxies — trusts the proxy in front of Apache
//
//  Apache sits behind the stack's Caddy endpoint, which
//  passes on the X-Forwarded-For of the public proxy in
//  front of it and sets X-Forwarded-Proto to https. Every
//  address is trusted as a proxy, so Laravel takes the
//  client address and the scheme from those headers — the
//  URLs it builds are https, and request()->ip() is the
//  visitor's address.
//
//  Used by:
//    - app/Http/Kernel.php — the global stack
// -----------------------------------------------------------


namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;








// -----------------------------------------------------------
// TrustProxies
// -----------------------------------------------------------
//
// Laravel's middleware with the two settings below.
//
// Used by:
//   - app/Http/Kernel.php — the global stack
// -----------------------------------------------------------

class TrustProxies extends Middleware
{

    // The proxies trusted: every address, as Apache sits on
    // the stack's internal network, where the endpoint is
    // the only way in
    protected $proxies = '*';

    // The forwarded headers read from a trusted proxy
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}
