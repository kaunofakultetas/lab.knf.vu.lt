<?php

// -----------------------------------------------------------
//  [*] ValidateVerify — the e-mail verification link check
//
//  Lets a verification link through only when its token
//  matches the verify_token of exactly one local account —
//  one without a VU id, as SSO accounts are never verified
//  by mail. Any other token is a 404.
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
use App\Models\User;








// -----------------------------------------------------------
// ValidateVerify
// -----------------------------------------------------------
//
// One method, handle.
//
// Used by:
//   - app/Http/Kernel.php — the validate.verify alias
// -----------------------------------------------------------

class ValidateVerify
{






    // -----------------------------------------------------------
    // handle
    // -----------------------------------------------------------
    //
    // Passes the request when one local account holds the
    // route's token, an empty or missing token never matching,
    // and answers 404 otherwise.
    //
    // Used by:
    //   - the validate.verify alias — GET /verify/{verify_token}
    // -----------------------------------------------------------

    public function handle($request, Closure $next)
    {
        $verify_token = $request->route('verify_token');

        if(User::where(['verify_token'=>$verify_token])->where('verify_token', '<>', '')->whereNotNull('verify_token')->whereNull('vu_id')->count() == 1) {
            return $next($request);
        } else {
            abort(404);
        }
    }
}
