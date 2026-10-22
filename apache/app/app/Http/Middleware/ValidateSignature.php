<?php

// -----------------------------------------------------------
//  [*] ValidateSignature — the signed-URL check
//
//  Laravel's check of signed URLs under the app's name. Its
//  alias, signed, is named by no route, so it never runs.
//
//  Used by:
//    - app/Http/Kernel.php — the signed alias, which no
//      route names
// -----------------------------------------------------------


namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ValidateSignature as Middleware;








// -----------------------------------------------------------
// ValidateSignature
// -----------------------------------------------------------
//
// Laravel's middleware with an empty except list.
//
// Used by:
//   - app/Http/Kernel.php — the signed alias
// -----------------------------------------------------------

class ValidateSignature extends Middleware
{

    // The query parameters the signature leaves out — none
    protected $except = [
    ];
}
