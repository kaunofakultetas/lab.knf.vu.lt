<?php

// -----------------------------------------------------------
//  [*] GroupsUsers — the members of a group
//
//  One cyber_users_groups row per member an admin added to
//  a group. Members are VU user ids, not account ids: an
//  admin can add students before they first sign in, and a
//  player with a local account can never be a member.
//
//  Used by:
//    - Admins\UsersGroupsController — the member list, its
//      add dialog and the removals
//    - Admins\UsersController::destroy — which deletes by a
//      user_id column the table does not have (LAB-11)
//    - GroupExists (Http/Middleware/) and
//      Users\BaseController — who may open a group, and the
//      groups of the sidebar
//    - ApiController, which no route reaches
// -----------------------------------------------------------


namespace App\Models;

use Illuminate\Database\Eloquent\Model;








// -----------------------------------------------------------
// GroupsUsers
// -----------------------------------------------------------
//
// Columns: id, group_id and vu_id.
//
// Used by:
//   - Admins\UsersGroupsController, Admins\UsersController,
//     GroupExists, Users\BaseController and ApiController
// -----------------------------------------------------------

class GroupsUsers extends Model
{

    // The table, which has no created_at / updated_at
    // columns; rememberTokenName belongs to the account
    // models, a plain model never reads it
    protected $table = "cyber_users_groups";
    public $timestamps = false;
    protected $rememberTokenName = false;

    // The columns, the id included
    protected $fillable = [
        'id',
        'group_id',
        'vu_id'
    ];

    // Empty: toArray hands out every column
    protected $hidden = [];

    // The column types; the VU id is text
    protected $casts = [
        'id' => 'integer',
        'group_id' => 'integer',
        'vu_id' => 'string'
    ];
}
