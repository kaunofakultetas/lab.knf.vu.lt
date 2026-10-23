<?php

// -----------------------------------------------------------
//  [*] User — the player accounts
//
//  One cyber_users row per player. A local account
//  registers with a username, e-mail and password and stays
//  inactive (status 0) until the link of its verify_token
//  mail is opened; an SSO account is created active at the
//  first VU login, with vu_id set, the VU user id as
//  username and an empty password no sign-in matches.
//  points is the sum of the player's solves, kept by
//  submit_answer; user_type is 1 for an individual and 2
//  for a team. remember_token holds the SHA-256 hash of the
//  password-reminder token, never Laravel's remember-me one
//  — the login never asks to be remembered.
//
//  Used by:
//    - config/auth.php — the users provider, behind the
//      users guard
//    - Users\RegisterController, Users\UserAuthController
//      and Users\ChallengesController — accounts, logins
//      and points
//    - Admins\UsersController — the accounts pages of the
//      control panel
//    - GroupExists, ValidateReset and ValidateVerify
//      (Http/Middleware/)
//    - Users\BaseController, Admins\LeaderboardController,
//      Admins\EmailsController::sync_with_lists and Groups
//      (this folder)
//    - database/factories/UserFactory.php
// -----------------------------------------------------------


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;








// -----------------------------------------------------------
// User
// -----------------------------------------------------------
//
// Laravel's authenticatable user on cyber_users. The e-mail
// verification is the app's own (verify_token), not
// Laravel's.
//
//   getUsername    — the username
//   scopeByRating  — dead: a rank lookup that cannot run
//   ratingList     — dead: the same query, thrown away
//   ratingList10   — dead: the top ten, never called
//
// Used by:
//   - config/auth.php — the users provider
//   - the player and control-panel controllers
// -----------------------------------------------------------

class User extends Authenticatable
{

    // Sanctum tokens, the factory and notifications
    use HasApiTokens, HasFactory, Notifiable;

    // The table, which has no created_at / updated_at columns
    public $timestamps = false;
    protected $table = 'cyber_users';

    // The columns registration, the SSO login and the
    // control panel write
    protected $fillable = [
        'username',
        'user_type',
        'vu_id',
        'display_name',
        'email',
        'password',
        'points',
        'status',
        'verify_token',
        'last_login',
        'remember_token',
        'newsletter',
        'last_ip',
    ];

    // Left out of toArray and JSON: the password hash and the
    // two tokens
    protected $hidden = [
        'password',
        'remember_token',
        'verify_token'
    ];

    // The column types; last_login stays the database's text
    protected $casts = [
        'points' => 'integer',
        'user_type' => 'integer',
        'status' => 'integer',
        'newsletter' => 'integer',
        'last_login' => 'string',
        'last_ip' => 'string',
    ];






    // -----------------------------------------------------------
    // getUsername
    // -----------------------------------------------------------
    //
    // The account's username. Its one caller builds the history
    // line ChallengesHistory then drops, as it has no such
    // column.
    //
    // Used by:
    //   - Users\ChallengesController::submit_answer
    // -----------------------------------------------------------

    function getUsername()
    {
        return $this->username;
    }






    // -----------------------------------------------------------
    // scopeByRating
    // -----------------------------------------------------------
    //
    // Dead code from another project: the position of the
    // player signed in on a mem guard in a points ranking
    // numbered with a MySQL session variable. It cannot run
    // here — DB and Auth are not imported in this file, and
    // the app has no mem guard.
    //
    // Used by:
    //   - nothing at the moment
    // -----------------------------------------------------------

    public static function scopeByRating($query)
    {
        $data = json_decode($query->selectRaw("@row_number:=@row_number+1 AS position, cyber_users.*")
            ->from(DB::raw("cyber_users, (SELECT @row_number:=0) AS t WHERE points > 0"))
            ->orderBy('points', 'desc')
            ->get(), 1);

        foreach ($data as $k => $v) {
            if ($v['id'] == Auth::guard('mem')->id()) {
                return $v['position'];
            }
        }
    }






    // -----------------------------------------------------------
    // ratingList
    // -----------------------------------------------------------
    //
    // Dead code: the ranking query of scopeByRating, whose
    // result is decoded and then neither kept nor returned.
    //
    // Used by:
    //   - nothing at the moment
    // -----------------------------------------------------------

    public static function ratingList($query)
    {
        $data = json_decode($query->selectRaw("@row_number:=@row_number+1 AS position, cyber_users.*")
            ->from(DB::raw("cyber_users, (SELECT @row_number:=0) AS t WHERE points > 0"))
            ->orderBy('points', 'desc')
            ->get(), 1);
    }






    // -----------------------------------------------------------
    // ratingList10
    // -----------------------------------------------------------
    //
    // Dead code: the ten best players of the same ranking, as
    // arrays. Like scopeByRating, it would fail on the missing
    // DB import.
    //
    // Used by:
    //   - nothing at the moment
    // -----------------------------------------------------------

    public static function ratingList10($query)
    {
        $data = json_decode($query->selectRaw("@row_number:=@row_number+1 AS position, cyber_users.*")
            ->from(DB::raw("cyber_users, (SELECT @row_number:=0) AS t WHERE points > 0"))
            ->orderBy('points', 'desc')
            ->take(10)
            ->get(), 1);

        return $data;
    }
}
