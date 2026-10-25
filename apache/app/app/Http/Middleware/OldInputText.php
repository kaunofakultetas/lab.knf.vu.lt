<?php

// -----------------------------------------------------------
//  [*] OldInputText — refused forms refill from text only
//
//  A refused form is flashed into the session for the next
//  page, which refills its fields with old() — printed with
//  {{ }} or compared as a string in a select, and every
//  field so refilled is a single value. A field sent as a
//  list, which only a hand-made request sends, would come
//  back as an array: htmlspecialchars refuses it, a string
//  compare cannot convert it, and the next page is a 500.
//  laravelcollective's FormBuilder, which the views used
//  before 2026-10-24, took the list's first value; here the
//  list is dropped from the flashed input, so the field
//  comes back empty, after any controller or the exception
//  handler has flashed it.
//
//  Used by:
//    - app/Http/Kernel.php — the web group
// -----------------------------------------------------------


namespace App\Http\Middleware;

use Closure;








// -----------------------------------------------------------
// OldInputText
// -----------------------------------------------------------
//
// One method, handle.
//
// Used by:
//   - app/Http/Kernel.php — the web group
// -----------------------------------------------------------

class OldInputText
{






    // -----------------------------------------------------------
    // handle
    // -----------------------------------------------------------
    //
    // Lets the request run, then drops every list from the
    // old input in the session — the one this request flashed,
    // or the one it was shown — before the session is saved.
    //
    // Used by:
    //   - the web group, on every page
    // -----------------------------------------------------------

    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if ($request->hasSession() && is_array($old = $request->session()->get('_old_input'))) {
            $request->session()->put('_old_input', array_filter($old, fn ($value) => ! is_array($value)));
        }

        return $response;
    }
}
