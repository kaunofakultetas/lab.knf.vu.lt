<?php

// -----------------------------------------------------------
//  [*] console — the closure commands of Artisan
//
//  Laravel's skeleton command, the app's only one of its
//  own: inspire, which prints an inspiring quote.
//
//  Used by:
//    - Console\Kernel::commands — loads it for Artisan
// -----------------------------------------------------------


use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

// The inspire command: a quote from Laravel's collection
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
