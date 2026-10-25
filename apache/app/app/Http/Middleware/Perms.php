<?php

// -----------------------------------------------------------
//  [*] Perms — the main-admin check of the control panel
//
//  Keeps the main-only parts of the control panel — the
//  categories, partners, pages, mail templates and settings,
//  account edits, status and deletion, challenge status — to
//  main admins. Anyone else gets "You don't have permissions
//  to do this!": a refused page on the dashboard, a refused
//  form back on the page it came from, a refused dialog in
//  its modal.
//
//  Used by:
//    - app/Http/Kernel.php — the perms alias
//    - routes/web.php — the main-only control-panel routes
// -----------------------------------------------------------


namespace App\Http\Middleware;

use Closure;
use Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;








// -----------------------------------------------------------
// Perms
// -----------------------------------------------------------
//
// One method, handle.
//
// Used by:
//   - app/Http/Kernel.php — the perms alias
// -----------------------------------------------------------

class Perms
{






    // -----------------------------------------------------------
    // handle
    // -----------------------------------------------------------
    //
    // Passes the request when the signed-in admin's account
    // is a main admin's, and refuses it with the error
    // otherwise: a dialog — a GET under /dialog/ — answers
    // with the error itself, the admins.dialogs.refused view,
    // as the panel loads a dialog into the modal of the page
    // it was opened from and would show there whatever page a
    // redirect led to; another page goes to the dashboard, as
    // back from a page the browser reached from a main-only
    // page is that page again, refused again, and would
    // redirect to itself until the browser gave up; a form
    // goes back, which, should it be a main-only page, ends on
    // the dashboard the next step. The flag is read from the
    // account on every request, so rights taken away end at
    // once, not at the next sign-in.
    //
    // Used by:
    //   - the perms alias — the main-only routes
    // -----------------------------------------------------------

    public function handle($request, Closure $next)
    {
        if(Auth::guard('admins')->user()?->main == 1){
            return $next($request);
        }

        if ($request->isMethod('GET') && str_contains($request->path(), '/dialog/')) {
            return response()->view('admins.dialogs.refused', ['error' => 'You don\'t have permissions to do this!']);
        }

        if ($request->isMethod('GET')) {
            return redirect()->route('admin_dashboard')->withErrors(['You don\'t have permissions to do this!']);
        }

        return back()->withErrors(['You don\'t have permissions to do this!']);
    }
}
