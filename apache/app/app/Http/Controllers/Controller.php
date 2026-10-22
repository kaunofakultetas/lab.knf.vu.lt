<?php

// -----------------------------------------------------------
//  [*] Controller — the base of every controller
//
//  Laravel's skeleton base controller: the routing
//  controller plus the authorize, dispatch and validate
//  helpers its traits bring. Most of the app's controllers
//  extend it through the player and control-panel bases.
//
//  Used by:
//    - Users\BaseController and Admins\BaseController — the
//      bases of the player and control-panel controllers
//    - Users\RegisterController, Admins\AdminAuthController,
//      HomeController and ApiController — directly
// -----------------------------------------------------------


namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;








// -----------------------------------------------------------
// Controller
// -----------------------------------------------------------
//
// Adds nothing of its own to Laravel's routing controller
// beyond the three helper traits.
//
// Used by:
//   - Users\BaseController and Admins\BaseController
//   - Users\RegisterController, Admins\AdminAuthController,
//     HomeController and ApiController
// -----------------------------------------------------------

class Controller extends BaseController
{

    // authorize, dispatch and validate, for every controller
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
}
