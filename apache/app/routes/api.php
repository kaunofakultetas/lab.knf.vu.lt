<?php

// -----------------------------------------------------------
//  [*] api — the API routes
//
//  Laravel's skeleton API route, loaded under /api with the
//  api group's rate limit: the signed-in user as JSON,
//  behind Sanctum's guard. Nothing of the lab uses it, and
//  it cannot answer anything but a server error (LAB-05).
//
//    GET  /api/user
//
//  Used by:
//    - RouteServiceProvider::boot — loads it under the api
//      middleware group and the /api prefix
// -----------------------------------------------------------


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// The signed-in user.
//
// Known bug LAB-05 (low): every request ends in a server
// error — auth:sanctum looks for the web guard that
// config/sanctum.php names, which config/auth.php does not
// define (api.php:29). Fix: delete the route, or point
// sanctum.guard at the users guard.
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
