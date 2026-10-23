<?php

// -----------------------------------------------------------
//  [*] Kernel — the middleware every request passes
//
//  The HTTP kernel of the app: the global stack every
//  request goes through, the web and api groups the route
//  files are loaded under, and the aliases the routes name
//  middleware by. Every request starts by copying the
//  leaderboard into Redis (UpdateRankingTable), and
//  Laravel's TrustHosts is switched off.
//
//  Used by:
//    - bootstrap/app.php — bound as the HTTP kernel
//    - public/index.php — runs every request through it
// -----------------------------------------------------------


namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;








// -----------------------------------------------------------
// Kernel
// -----------------------------------------------------------
//
// Laravel's HTTP kernel with the app's three lists. Most
// entries are Laravel's middleware or the app's thin
// subclasses of them in app/Http/Middleware; the app's own
// are UpdateRankingTable in the global stack and the checks
// among the aliases.
//
// Used by:
//   - bootstrap/app.php — bound as the HTTP kernel
//   - public/index.php — runs every request through it
// -----------------------------------------------------------

class Kernel extends HttpKernel
{

    // The global stack, run in this order on every request.
    // UpdateRankingTable reads the cyber_users_ranks view and
    // stores it in Redis as ranking_table, where the player
    // sidebar and both leaderboards read it — one query per
    // request, guests and admins included
    protected $middleware = [
        // TrustHosts is switched off: any Host header is answered
        \App\Http\Middleware\TrustProxies::class,
        \Illuminate\Http\Middleware\HandleCors::class,
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \App\Http\Middleware\UpdateRankingTable::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    ];

    // The groups RouteServiceProvider loads the route files
    // under: web for routes/web.php, api for routes/api.php,
    // whose one route is the skeleton's /api/user
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            // Sanctum's EnsureFrontendRequestsAreStateful is
            // switched off: api requests carry no session
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    // The names routes give middleware — Laravel 10 calls the
    // list middlewareAliases and still reads this old name.
    // auth, auth.admin, guest and signed are the app's
    // versions of Laravel's; reg.status, validate.ifsolved,
    // event.status, validate.verify, validate.reset,
    // saml_auth, saml_auth_admin, group.exists, r_exists and
    // perms are the app's own checks; the rest are Laravel's.
    // routes/web.php uses every app alias but signed, the api
    // group uses throttle, and auth.session, cache.headers,
    // can, password.confirm and verified are named by nothing.
    protected $routeMiddleware = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.admin' => \App\Http\Middleware\AuthenticateAdmin::class,
        'reg.status' => \App\Http\Middleware\RegStatus::class,
        'validate.ifsolved' => \App\Http\Middleware\ValidateChallengeSolved::class,
        'event.status' => \App\Http\Middleware\EventStatus::class,
        'auth.session' => \Illuminate\Session\Middleware\AuthenticateSession::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticatedWithGuard::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'signed' => \App\Http\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        'validate.verify' => \App\Http\Middleware\ValidateVerify::class,
        'validate.reset' => \App\Http\Middleware\ValidateReset::class,
        'saml_auth' => \App\Http\Middleware\SamlAuth::class,
        'saml_auth_admin' => \App\Http\Middleware\SamlAuthAdmin::class,
        'group.exists' => \App\Http\Middleware\GroupExists::class,
        'r_exists' => \App\Http\Middleware\R_Exists::class,
        'perms' => \App\Http\Middleware\Perms::class
    ];
}
