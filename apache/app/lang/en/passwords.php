<?php

// -----------------------------------------------------------
//  [*] lang/en/passwords.php — Laravel's reset messages
//
//  The messages of Laravel's password broker. The app's
//  reminder flow is its own (Users\RegisterController) and
//  never calls the broker, so these are never shown.
//
//  Used by:
//    - nothing at the moment — Laravel's password broker
//      would
// -----------------------------------------------------------


return [

    // The messages, by key
    'reset' => 'Your password has been reset!',
    'sent' => 'We have emailed your password reset link!',
    'throttled' => 'Please wait before retrying.',
    'token' => 'This password reset token is invalid.',
    'user' => "We can't find a user with that email address.",
];
