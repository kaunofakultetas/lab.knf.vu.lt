<?php

// -----------------------------------------------------------
//  [*] Settings — the lab's settings rows
//
//  Two cyber_settings rows the code reads by id: 1,
//  event_date, holds the event's time range as "start -
//  end" in val1; 2, reg_open, holds '1' while registration
//  is open and '0' when it is closed. The control panel's
//  settings page writes both; val2 is never used.
//
//  Used by:
//    - Admins\SettingsController — the settings page and its
//      two forms
//    - Users\BaseController — the event time range of the
//      player pages
//    - RegStatus (Http/Middleware/) and
//      Users\RegisterController::create — the registration
//      switch
// -----------------------------------------------------------


namespace App\Models;

use Illuminate\Database\Eloquent\Model;








// -----------------------------------------------------------
// Settings
// -----------------------------------------------------------
//
// Columns: name, val1 and val2.
//
// Used by:
//   - Admins\SettingsController, Users\BaseController,
//     RegStatus and Users\RegisterController
// -----------------------------------------------------------

class Settings extends Model
{

    // The table, which has no created_at / updated_at
    // columns; rememberTokenName belongs to the account
    // models, a plain model never reads it
    protected $table = "cyber_settings";
    public $timestamps = false;
    protected $rememberTokenName = false;

    // The columns of a setting
    protected $fillable = [
        'name',
        'val1',
        'val2'
    ];

    // Empty: toArray hands out every column
    protected $hidden = [];

    // Every column is text
    protected $casts = [
        'name' => 'string',
        'val1' => 'string',
        'val2' => 'string'
    ];
}
