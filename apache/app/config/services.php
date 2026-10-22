<?php

// -----------------------------------------------------------
//  [*] config/services.php — third-party service keys
//
//  Laravel's stock entries for the Mailgun, Postmark and
//  SES mailers, none of them set or used: the mails go by
//  SMTP to Mailjet (config/mail.php). The keys the app does
//  use — reCAPTCHA, Mailjet's API — are read elsewhere.
//
//  Used by:
//    - nothing at the moment — the mailers that would read
//      it are unused
// -----------------------------------------------------------


return [

    // Mailgun's API mailer
    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    // Postmark's API mailer
    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    // Amazon SES's mailer
    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
];
