<?php

// -----------------------------------------------------------
//  [*] AuthServiceProvider — model policies and gates
//
//  Laravel's skeleton provider for authorisation, with
//  nothing in it: the app defines no policies and no gates.
//  Who may do what is decided by the guards (users and
//  admins, config/auth.php) and by the perms and r_exists
//  middleware.
//
//  Used by:
//    - config/app.php — the providers list
// -----------------------------------------------------------


namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;








// -----------------------------------------------------------
// AuthServiceProvider
// -----------------------------------------------------------
//
// Registers the empty policy list at boot.
//
// Used by:
//   - config/app.php — the providers list
// -----------------------------------------------------------

class AuthServiceProvider extends ServiceProvider
{

    // Model classes mapped to their policy classes — none
    protected $policies = [];






    // -----------------------------------------------------------
    // boot
    // -----------------------------------------------------------
    //
    // Hands the (empty) policy list to the gate.
    //
    // Used by:
    //   - Laravel, once every provider is registered
    // -----------------------------------------------------------

    public function boot()
    {
        $this->registerPolicies();
    }
}
