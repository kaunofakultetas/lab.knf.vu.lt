<?php

// -----------------------------------------------------------
//  [*] Emails — the mail templates of the control panel
//
//  One cyber_emails row per template a main admin writes:
//  a name for the list, the subject and the HTML content,
//  and sent, which turns 1 once the template has gone out
//  to the registered players' mailing list.
//
//  Used by:
//    - Admins\EmailsController — the mail templates page,
//      its forms, the test mail and the send
// -----------------------------------------------------------


namespace App\Models;

use Illuminate\Database\Eloquent\Model;








// -----------------------------------------------------------
// Emails
// -----------------------------------------------------------
//
// Columns: name, subject, content and sent.
//
// Used by:
//   - Admins\EmailsController — index, store, edit, update,
//     destroy, sending_test and send
// -----------------------------------------------------------

class Emails extends Model
{

    // The table, which has no created_at / updated_at
    // columns; rememberTokenName belongs to the account
    // models, a plain model never reads it
    protected $table = "cyber_emails";
    public $timestamps = false;
    protected $rememberTokenName = false;

    // The columns the template form fills
    protected $fillable = [
        'name',
        'subject',
        'content',
        'sent'
    ];
}
