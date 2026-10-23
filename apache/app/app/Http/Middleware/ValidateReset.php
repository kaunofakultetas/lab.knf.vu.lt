<?php

// -----------------------------------------------------------
//  [*] ValidateReset — the password-reminder link check
//
//  Lets a reminder link through only when the SHA-256 hash
//  of its token matches the remember_token of exactly one
//  account, where RegisterController::forgotpassword stores
//  it. Any other token is a 404.
//
//  Used by:
//    - app/Http/Kernel.php — the validate.reset alias
//    - routes/web.php — GET /reset/{reset_token}, the link
//      of the reminder mail
// -----------------------------------------------------------


namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\User;








// -----------------------------------------------------------
// ValidateReset
// -----------------------------------------------------------
//
// One method, handle.
//
// Used by:
//   - app/Http/Kernel.php — the validate.reset alias
// -----------------------------------------------------------

class ValidateReset
{






    // -----------------------------------------------------------
    // handle
    // -----------------------------------------------------------
    //
    // Passes the request when one account holds the hash of
    // the route's token, an empty or missing token never
    // matching, and answers 404 otherwise.
    //
    // Known bug LAB-25 (medium): a reminder link never expires
    // — nothing records when it was mailed, so a token still
    // in remember_token passes however old it is
    // (ValidateReset.php:75). Fix: record when the reminder
    // was sent and refuse links older than an hour, as
    // Laravel's own password broker does.
    //
    // Used by:
    //   - the validate.reset alias — GET /reset/{reset_token}
    // -----------------------------------------------------------

    public function handle($request, Closure $next)
    {
        $reset_token = $request->route('reset_token');

        if(User::where(['remember_token'=>hash('sha256', $reset_token)])->where('remember_token', '<>', '')->whereNotNull('remember_token')->count() == 1) {
            return $next($request);
        } else {
            abort(404);
        }
    }
}
