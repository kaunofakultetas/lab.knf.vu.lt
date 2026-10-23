<?php

// -----------------------------------------------------------
//  [*] RouteServiceProvider — loads the route files
//
//  Loads routes/api.php under the /api prefix and the api
//  middleware group, and routes/web.php under the web group,
//  and defines the rate limit the api group applies.
//
//  Used by:
//    - config/app.php — the providers list
// -----------------------------------------------------------


namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;








// -----------------------------------------------------------
// RouteServiceProvider
// -----------------------------------------------------------
//
//   boot                    — the two route files
//   configureRateLimiting   — the api limiter
//
// Used by:
//   - config/app.php — the providers list
// -----------------------------------------------------------

class RouteServiceProvider extends ServiceProvider
{

    // Laravel's default landing page after a login. Nothing
    // reads it — the app has no /home route, and the logins
    // redirect to their own dashboards
    public const HOME = '/home';






    // -----------------------------------------------------------
    // boot
    // -----------------------------------------------------------
    //
    // Defines the rate limiter, then loads the API routes
    // under /api with the api group and the site's routes
    // with the web group — sessions, cookies and CSRF.
    //
    // Used by:
    //   - Laravel, once every provider is registered
    // -----------------------------------------------------------

    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }






    // -----------------------------------------------------------
    // configureRateLimiting
    // -----------------------------------------------------------
    //
    // The api limiter: 60 requests a minute per signed-in
    // user, or per client address for guests. Only the api
    // group's throttle:api uses it; the site's own routes
    // have no limit, by design.
    //
    // Used by:
    //   - boot (above)
    // -----------------------------------------------------------

    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
