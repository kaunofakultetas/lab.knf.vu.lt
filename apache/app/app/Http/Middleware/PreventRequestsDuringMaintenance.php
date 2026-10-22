<?php

// -----------------------------------------------------------
//  [*] PreventRequestsDuringMaintenance — the maintenance stop
//
//  Laravel's maintenance-mode check: while artisan down is
//  in force, every request gets the 503 page, as no address
//  is left reachable.
//
//  Used by:
//    - app/Http/Kernel.php — the global stack
// -----------------------------------------------------------


namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance as Middleware;








// -----------------------------------------------------------
// PreventRequestsDuringMaintenance
// -----------------------------------------------------------
//
// Laravel's middleware with an empty except list.
//
// Used by:
//   - app/Http/Kernel.php — the global stack
// -----------------------------------------------------------

class PreventRequestsDuringMaintenance extends Middleware
{

    // The addresses still answered during maintenance — none
    protected $except = [
    ];
}
