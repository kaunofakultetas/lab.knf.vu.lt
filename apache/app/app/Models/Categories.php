<?php

// -----------------------------------------------------------
//  [*] Categories — the challenge categories
//
//  One cyber_categories row per category the challenges are
//  filed under, kept by main admins on the categories page;
//  the schema seeds seven of them.
//
//  Used by:
//    - Admins\CategoriesController — the categories page and
//      its dialogs
//    - Admins\ChallengesController::categories — the
//      categories dialog of a challenge
//    - Users\ChallengesController::index — the dashboard's
//      category filter
// -----------------------------------------------------------


namespace App\Models;

use Illuminate\Database\Eloquent\Model;








// -----------------------------------------------------------
// Categories
// -----------------------------------------------------------
//
// Columns: id and name.
//
// Used by:
//   - Admins\CategoriesController, Admins\ChallengesController
//     and Users\ChallengesController
// -----------------------------------------------------------

class Categories extends Model
{

    // The table, which has no created_at / updated_at
    // columns; rememberTokenName belongs to the account
    // models, a plain model never reads it
    protected $table = "cyber_categories";
    public $timestamps = false;
    protected $rememberTokenName = false;

    // The columns, the id included
    protected $fillable = [
        'id',
        'name'
    ];

    // Empty: toArray hands out every column
    protected $hidden = [];

    // The column types
    protected $casts = [
        'id' => 'integer',
        'name' => 'string'
    ];
}
