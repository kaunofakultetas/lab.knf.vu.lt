<?php

// -----------------------------------------------------------
//  [*] PageController — the lab's content pages
//
//  Shows a page a main admin wrote in the control panel, by
//  its alias, to anyone: the route asks for no sign-in. An
//  unknown alias sends the visitor to the login page.
//
//  Used by:
//    - routes/web.php — GET /page/{alias}, which no view of
//      the app links to
// -----------------------------------------------------------


namespace App\Http\Controllers\Users;

use App\Http\Controllers\Users\BaseController;
use App\Http\Controllers\Users\SettingsController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\Pages;








// -----------------------------------------------------------
// PageController
// -----------------------------------------------------------
//
//   __construct — the base's layout data
//   show        — one page
//
// Used by:
//   - routes/web.php — GET /page/{alias}
// -----------------------------------------------------------

class PageController extends BaseController
{






    // -----------------------------------------------------------
    // __construct
    // -----------------------------------------------------------
    //
    // Only runs the base constructor, which shares the
    // layout's data.
    //
    // Used by:
    //   - Laravel, when it builds the controller for a request
    // -----------------------------------------------------------

    public function __construct()
    {
        parent::__construct();
    }






    // -----------------------------------------------------------
    // show
    // -----------------------------------------------------------
    //
    // GET /page/{alias}
    //
    // The users.page view with the page's name and content;
    // the login page when no page has the alias.
    //
    // Used by:
    //   - whoever has the address — no view links to it
    // -----------------------------------------------------------

    public function show($alias)
    {
        $validator = Validator::make(['alias'=>$alias], [
            'alias' => ['required', 'string', 'exists:cyber_pages,alias'],
        ]);

        if ($validator->fails()) {
            return redirect()->route('login_users');
        }

        return view('users.page', ['data'=>Pages::where(['alias'=>$alias])->get()->first()->toArray()]);
    }
}
