<?php

// -----------------------------------------------------------
//  [*] Pages — the lab's content pages
//
//  One cyber_pages row per page a main admin writes in the
//  control panel: its name, the HTML content and the alias
//  its address uses (/page/{alias}). The alias is hidden,
//  so the page data handed to the view leaves it out.
//
//  Used by:
//    - Admins\PagesController — the pages list and its forms
//    - Users\PageController::show — a page by its alias
// -----------------------------------------------------------


namespace App\Models;

use Illuminate\Database\Eloquent\Model;








// -----------------------------------------------------------
// Pages
// -----------------------------------------------------------
//
// Columns: id, name, content and alias.
//
// Used by:
//   - Admins\PagesController and Users\PageController
// -----------------------------------------------------------

class Pages extends Model
{

    // The table, which has no created_at / updated_at
    // columns; rememberTokenName belongs to the account
    // models, a plain model never reads it
    protected $table = "cyber_pages";
    public $timestamps = false;
    protected $rememberTokenName = false;

    // The columns, the id included
    protected $fillable = [
        'id',
        'name',
        'content',
        'alias'
    ];

    // Left out of toArray
    protected $hidden = ['alias'];

    // Every column but the id is text
    protected $casts = [
        'id' => 'integer',
        'name' => 'string',
        'content' => 'string',
        'alias' => 'string'
    ];
}
