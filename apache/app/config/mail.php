<?php

// -----------------------------------------------------------
//  [*] config/mail.php — how the app's mails go out
//
//  Every mail — the verification and reminder links, new
//  passwords, the mail templates — goes by SMTP to Mailjet's
//  relay, in-v3.mailjet.com on port 587 with TLS, as the
//  image's .env sets. The relay's login is no secret of
//  that file: it points MAIL_USERNAME and MAIL_PASSWORD at
//  MAILJET_APIKEY and MAILJET_APISECRET, which
//  docker-compose.yml passes in from the stack's own .env.
//  The stack's exit is the one way out to the relay
//  (exit/Caddyfile, and the host pin of docker-compose.yml).
//  The sender is noreply@lab.knf.vu.lt, "VU KNF Lab"
//  (docker-compose.yml). The tests swap the mailer for the
//  in-memory one (phpunit.xml).
//
//  Used by:
//    - Laravel's mailer — every mail the app sends, through
//      the mailables of app/Mail
// -----------------------------------------------------------


return [

    // The mailer, from MAIL_MAILER — not set, so smtp; the
    // .env's MAIL_DRIVER is the old name, which Laravel 10 no
    // longer reads
    'default' => env('MAIL_MAILER', 'smtp'),

    // The mailers. smtp is the one in use: its host, port
    // and encryption from the .env, its login the Mailjet API
    // key and secret the .env refers to. mailjet names no
    // transport, so no mailer can use it — EmailsController
    // builds its own Mailjet API client for the list sync.
    // The rest are Laravel's stock mailers, unused
    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'host' => env('MAIL_HOST', 'smtp.mailgun.org'),
            'port' => env('MAIL_PORT', 587),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
        ],

        'mailjet' => [
            'key' => env('MAILJET_APIKEY'),
            'secret' => env('MAILJET_APISECRET'),
            'transactional' => [
                'call' => true,
                'options' => [
                    'url' => 'api.mailjet.com',
                    'version' => 'v3.1',
                    'call' => true,
                    'secured' => true
                ]
            ],
            'common' => [
                'call' => true,
                'options' => [
                    'url' => 'api.mailjet.com',
                    'version' => 'v3',
                    'call' => true,
                    'secured' => true
                ]
            ]
        ],
        'ses' => [
            'transport' => 'ses',
        ],

        'mailgun' => [
            'transport' => 'mailgun',
        ],

        'postmark' => [
            'transport' => 'postmark',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
        ],
    ],

    // The sender of every mail, from MAIL_FROM_ADDRESS and
    // MAIL_FROM_NAME
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'noreply@lab.knf.vu.lt'),
        'name' => env('MAIL_FROM_NAME', 'VU KNF Lab'),
    ],

    // The theme and templates of Laravel's Markdown mails,
    // which the app does not send
    'markdown' => [
        'theme' => 'default',

        'paths' => [
            resource_path('views/vendor/mail'),
        ],
    ],
];
