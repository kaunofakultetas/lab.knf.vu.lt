<?php

// -----------------------------------------------------------
//  [*] Admin — the control-panel accounts
//
//  One cyber_admins row per admin. The VU single sign-on is
//  the only way in, so an admin is found by vu_id, the VU
//  user id; the email and password columns are left over
//  from a password login the panel no longer has. main is
//  1 for a main admin,
//  who may use every part of the control panel, and 0 for a
//  lab admin, who may only manage the groups and challenges
//  they created.
//
//  Used by:
//    - config/auth.php — the admins provider, behind the
//      admins guard
//    - AdminAuthController::check_admin — the SSO login
//    - Admins\BaseController — the vu_id and main flag the
//      control-panel views show
// -----------------------------------------------------------


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;








// -----------------------------------------------------------
// Admin
// -----------------------------------------------------------
//
// Laravel's authenticatable user on cyber_admins. The
// traits are the skeleton's: no admin has API tokens, no
// factory exists for the model, and no notification is
// sent to one.
//
// Used by:
//   - config/auth.php — the admins provider
//   - AdminAuthController and Admins\BaseController
// -----------------------------------------------------------

class Admin extends Authenticatable
{

    // Sanctum tokens, factories and notifications
    use HasApiTokens, HasFactory, Notifiable;

    // The table, which has no created_at / updated_at columns
    public $timestamps = false;
    protected $table = 'cyber_admins';

    // The columns an admin row is written with
    protected $fillable = [
        'vu_id',
        'email',
        'password',
        'main',
    ];

    // Left out of toArray and JSON
    protected $hidden = [
        'password',
        'remember_token',
    ];

    // Empty: every column comes back uncast, main included
    protected $casts = [
    ];
}
