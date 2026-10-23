<?php

// -----------------------------------------------------------
//  [*] Settings — the lab's settings rows
//
//  The cyber_settings rows, read by id: 2, reg_open, holds
//  '1' while registration is open, and the control panel's
//  settings page writes it — '1' from its ticked box, and
//  nothing (NULL) from an unticked one, which closes the
//  registration as any value but '1' does.
//  Row 1, event_date, is the time range of the one-off
//  event the lab grew out of; nothing reads it since the
//  event window was retired. val2 is never used.
//
//  Used by:
//    - Admins\SettingsController — the settings page and its
//      registration switch
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
//   - Admins\SettingsController, RegStatus and
//     Users\RegisterController
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
