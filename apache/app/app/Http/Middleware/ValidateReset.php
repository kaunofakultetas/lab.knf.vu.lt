<?php

// -----------------------------------------------------------
//  [*] ValidateReset — the password-reminder link check
//
//  Lets a reminder link through only when the SHA-256 hash
//  of its token matches the remember_token of exactly one
//  account, where RegisterController::forgotpassword stores
//  it, and the link was mailed within the last hour. Any
//  other token is a 404.
//
//  Used by:
//    - app/Http/Kernel.php — the validate.reset alias
//    - routes/web.php — GET and POST /reset/{reset_token},
//      the link of the reminder mail and the button of its
//      page
// -----------------------------------------------------------


namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\User;








// -----------------------------------------------------------
// ValidateReset
// -----------------------------------------------------------
//
// One method, handle, and the lifetime of a link.
//
// Used by:
//   - app/Http/Kernel.php — the validate.reset alias
// -----------------------------------------------------------

class ValidateReset
{

    // How long a reminder link stays good, in seconds — an
    // hour, as with Laravel's own password broker
    private const LIFETIME = 3600;






    // -----------------------------------------------------------
    // handle
    // -----------------------------------------------------------
    //
    // Passes the request when one account holds the hash of
    // the route's token, an empty or missing token never
    // matching, and the time the token ends with is at most
    // LIFETIME ago by Laravel's clock; answers 404 otherwise,
    // an expired link like an unknown one. A token without a
    // time — one mailed before links expired — reads as 1970.
    //
    // Used by:
    //   - the validate.reset alias — GET and POST
    //     /reset/{reset_token}
    // -----------------------------------------------------------

    public function handle($request, Closure $next)
    {
        $reset_token = $request->route('reset_token');

        // The Unix time the link was mailed, after the token's
        // last dash
        $mailed = (int) Str::afterLast($reset_token, '-');

        if(now()->getTimestamp() - $mailed <= self::LIFETIME && User::where(['remember_token'=>hash('sha256', $reset_token)])->where('remember_token', '<>', '')->whereNotNull('remember_token')->count() == 1) {
            return $next($request);
        } else {
            abort(404);
        }
    }
}
