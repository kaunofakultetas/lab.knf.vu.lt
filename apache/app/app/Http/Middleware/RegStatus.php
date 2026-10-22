<?php

// -----------------------------------------------------------
//  [*] RegStatus — the registration switch
//
//  Lets a registration through only while registration is
//  open: the reg_open row of cyber_settings (id 2), which the
//  control panel's settings page switches. While it is
//  closed, the registration POST answers 404.
//
//  Used by:
//    - app/Http/Kernel.php — the reg.status alias
//    - routes/web.php — POST /register
// -----------------------------------------------------------


namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\Settings;








// -----------------------------------------------------------
// RegStatus
// -----------------------------------------------------------
//
// One method, handle.
//
// Used by:
//   - app/Http/Kernel.php — the reg.status alias
// -----------------------------------------------------------

class RegStatus
{






    // -----------------------------------------------------------
    // handle
    // -----------------------------------------------------------
    //
    // Passes the request when the setting reads '1', and
    // answers 404 otherwise.
    //
    // Used by:
    //   - the reg.status alias — POST /register
    // -----------------------------------------------------------

    public function handle($request, Closure $next)
    {
        if(Settings::find(2)->val1 == '1') {
            return $next($request);
        } else {
            abort(404);
        }
    }
}
