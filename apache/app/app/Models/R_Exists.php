<?php

// -----------------------------------------------------------
//  [*] R_Exists — a stray, empty copy of the middleware
//
//  A file in the models folder that declares the middleware
//  class App\Http\Middleware\R_Exists, with every check
//  switched off. The autoloader never loads it: PSR-4 finds
//  the class in app/Http/Middleware/R_Exists.php, and
//  composer's optimised class map skips this file, whose
//  path does not match its namespace. Dead code, kept as
//  found.
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








// -----------------------------------------------------------
// R_Exists
// -----------------------------------------------------------
//
// One method, handle.
//
// Used by:
//   - nothing at the moment
// -----------------------------------------------------------

class R_Exists
{






    // -----------------------------------------------------------
    // handle
    // -----------------------------------------------------------
    //
    // Returns nothing, whatever the type.
    //
    // Used by:
    //   - nothing at the moment
    // -----------------------------------------------------------

    public function handle($request, Closure $next, $type)
    {
        // The one check it held, lm_cat for the learning-material
        // categories of another project, is switched off
    }
}
