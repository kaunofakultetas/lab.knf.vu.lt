<?php

// -----------------------------------------------------------
//  [*] channels — the broadcast channels
//
//  Laravel's skeleton channel: a user may listen on the
//  private channel named after their own id. The app
//  broadcasts nothing, and BroadcastServiceProvider, which
//  would load this file, is switched off, so it never runs.
//
//  Used by:
//    - nothing at the moment — BroadcastServiceProvider would
//      load it
// -----------------------------------------------------------


use Illuminate\Support\Facades\Broadcast;

// A user's own channel: only that user may listen
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
