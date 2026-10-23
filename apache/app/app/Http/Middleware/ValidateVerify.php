<?php

// -----------------------------------------------------------
//  [*] ValidateVerify — the e-mail verification link check
//
//  Lets a verification link through only when the SHA-256
//  hash of its token matches the verify_token of exactly
//  one local account — one without a VU id, as SSO
//  accounts are never verified by mail — and the link was
//  mailed within the last day. Any other token is a 404.
//
//  Used by:
//    - app/Http/Kernel.php — the validate.verify alias
//    - routes/web.php — GET /verify/{verify_token}, the link
//      of the registration mail
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
// ValidateVerify
// -----------------------------------------------------------
//
// One method, handle, and the lifetime of a link.
//
// Used by:
//   - app/Http/Kernel.php — the validate.verify alias
// -----------------------------------------------------------

class ValidateVerify
{

    // How long a verification link stays good, in seconds — a
    // day; signing in to an account never verified mails a
    // fresh one
    private const LIFETIME = 86400;






    // -----------------------------------------------------------
    // handle
    // -----------------------------------------------------------
    //
    // Passes the request when one local account holds the
    // hash of the route's token, an empty or missing token
    // never matching, and the time the token ends with is at
    // most LIFETIME ago by Laravel's clock; answers 404
    // otherwise, an expired link like an unknown one. A token
    // without a time — one mailed before links expired —
    // reads as 1970.
    //
    // Used by:
    //   - the validate.verify alias — GET /verify/{verify_token}
    // -----------------------------------------------------------

    public function handle($request, Closure $next)
    {
        $verify_token = $request->route('verify_token');

        // The Unix time the link was mailed, after the token's
        // last dash
        $mailed = (int) Str::afterLast($verify_token, '-');

        if(now()->getTimestamp() - $mailed <= self::LIFETIME && User::where(['verify_token'=>hash('sha256', $verify_token)])->where('verify_token', '<>', '')->whereNotNull('verify_token')->whereNull('vu_id')->count() == 1) {
            return $next($request);
        } else {
            abort(404);
        }
    }
}
