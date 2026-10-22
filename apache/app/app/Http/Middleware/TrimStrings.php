<?php

// -----------------------------------------------------------
//  [*] TrimStrings — trims the blanks around input fields
//
//  Laravel's input trimming: every request field loses its
//  leading and trailing blanks before validation, except
//  the password fields, whose blanks belong to the password.
//
//  Used by:
//    - app/Http/Kernel.php — the global stack
// -----------------------------------------------------------


namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TrimStrings as Middleware;








// -----------------------------------------------------------
// TrimStrings
// -----------------------------------------------------------
//
// Laravel's middleware with Laravel's password fields left
// out.
//
// Used by:
//   - app/Http/Kernel.php — the global stack
// -----------------------------------------------------------

class TrimStrings extends Middleware
{

    // The fields kept exactly as typed
    protected $except = [
        'current_password',
        'password',
        'password_confirmation',
    ];
}
