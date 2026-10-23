<?php

// -----------------------------------------------------------
//  [*] api — the API routes
//
//  Laravel's skeleton API route, loaded under /api with the
//  api group's rate limit: the signed-in user as JSON,
//  behind Sanctum's guard. Nothing of the lab uses it: the
//  api group starts no session and no token exists, so it
//  turns every client away with a 401.
//
//    GET  /api/user
//
//  Used by:
//    - RouteServiceProvider::boot — loads it under the api
//      middleware group and the /api prefix
// -----------------------------------------------------------


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// The signed-in user — in practice a 401 for everyone
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
