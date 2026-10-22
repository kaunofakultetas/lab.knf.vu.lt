<?php

// -----------------------------------------------------------
//  [*] config/hashing.php — password hashing
//
//  Passwords are hashed with bcrypt, at the cost
//  BCRYPT_ROUNDS sets — 10 unless set, 4 in the tests, to
//  keep them fast (phpunit.xml).
//
//  Used by:
//    - Hash::make and the guards' password checks — the
//      registration, the reminder's new password, the
//      control panel's account forms and the login
// -----------------------------------------------------------


return [

    // The algorithm of new hashes
    'driver' => 'bcrypt',

    // bcrypt's cost
    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 10),
    ],

    // Argon2's settings, unused while the driver is bcrypt
    'argon' => [
        'memory' => 65536,
        'threads' => 1,
        'time' => 4,
    ],
];
