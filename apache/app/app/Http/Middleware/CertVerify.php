<?php

// -----------------------------------------------------------
//  [*] CertVerify — an unused certificate ownership check
//
//  A leftover from another project: it would let a user of
//  a cert guard reach only their own certificate. Neither
//  the cert guard nor the App\Models\Certs model exists in
//  this app, and no alias names the class, so nothing runs
//  it. Dead code, kept as found.
//
//  Used by:
//    - nothing at the moment
// -----------------------------------------------------------


namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\Certs;








// -----------------------------------------------------------
// CertVerify
// -----------------------------------------------------------
//
// One method, handle.
//
// Used by:
//   - nothing at the moment
// -----------------------------------------------------------

class CertVerify
{






    // -----------------------------------------------------------
    // handle
    // -----------------------------------------------------------
    //
    // Would pass the request when exactly one certificate with
    // the route's public_id belongs to the signed-in cert
    // user, and answer 404 otherwise.
    //
    // Used by:
    //   - nothing at the moment
    // -----------------------------------------------------------

    public function handle($request, Closure $next)
    {
        $public_id =  $request->route('public_id');

        $user_id = Auth::guard('cert')->id();

        if (Certs::where(['public_id' => $public_id, 'user_id' => $user_id])->count() == 1) {
            return $next($request);
        } else {
            abort(404);
        }
    }
}
