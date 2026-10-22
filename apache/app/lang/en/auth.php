<?php

// -----------------------------------------------------------
//  [*] lang/en/auth.php — Laravel's sign-in messages
//
//  The messages of Laravel's own authentication: wrong
//  credentials, a wrong password, too many attempts. The
//  app's logins write their own messages ("Wrong login
//  credentials", "Your account is not active"), so these
//  are never shown.
//
//  Used by:
//    - nothing at the moment — Laravel's login scaffolding
//      would
// -----------------------------------------------------------


return [

    // The messages, by key; :seconds is the wait
    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',
];
