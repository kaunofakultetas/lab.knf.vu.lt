<?php

// -----------------------------------------------------------
//  [*] ChallengesCategories — the categories of a challenge
//
//  The link table between challenges and categories: one
//  cyber_challenges_categories row per category a challenge
//  is filed under. The categories dialog of a challenge
//  rewrites a challenge's rows; the player dashboard filters
//  by them.
//
//  Used by:
//    - Admins\ChallengesController — categories,
//      categories_store and destroy
//    - Admins\CategoriesController::destroy
//    - Users\ChallengesController::index — the category
//      names of the dashboard's cards and its filter
//    - Users\GroupsController::index — the category names
//      of the group page's cards
// -----------------------------------------------------------


namespace App\Models;

use Illuminate\Database\Eloquent\Model;








// -----------------------------------------------------------
// ChallengesCategories
// -----------------------------------------------------------
//
// Columns: challenge_id and cat_id.
//
// Used by:
//   - Admins\ChallengesController and
//     Admins\CategoriesController — the rows
//   - Users\ChallengesController and Users\GroupsController
//     — the category names of the challenge cards, and the
//     dashboard's filter
// -----------------------------------------------------------

class ChallengesCategories extends Model
{

    // The table, which has no created_at / updated_at
    // columns; rememberTokenName belongs to the account
    // models, a plain model never reads it
    protected $table = "cyber_challenges_categories";
    public $timestamps = false;
    protected $rememberTokenName = false;

    // The two ids of a link
    protected $fillable = [
        'challenge_id', 'cat_id'
    ];

    // Empty: toArray hands out every column
    protected $hidden = [
    ];

    // Both ids come back as integers
    protected $casts = [
        'challenge_id'=>'integer',
        'cat_id'=>'integer',
    ];
}
