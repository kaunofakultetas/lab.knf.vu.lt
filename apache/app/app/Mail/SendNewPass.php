<?php

// -----------------------------------------------------------
//  [*] SendNewPass — a new password, by mail
//
//  The mail that hands a player a password in plain text:
//  the one an admin typed for a new or edited account, or
//  the one generated when a password-reminder link is
//  opened (LAB-27). The view is users.mail.usernewpass.
//
//  Used by:
//    - Admins\UsersController::store and update — an account
//      the control panel created or gave a new password
//    - Users\RegisterController::reset_pass — the reminder
//      link
// -----------------------------------------------------------


namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;








// -----------------------------------------------------------
// SendNewPass
// -----------------------------------------------------------
//
//   __construct — takes the address and the password
//   build       — the message
//
// Used by:
//   - Admins\UsersController and Users\RegisterController
// -----------------------------------------------------------

class SendNewPass extends Mailable
{

    // The traits of Laravel's mailable skeleton
    use Queueable, SerializesModels;

    // The account's e-mail and its new password, under the
    // keys email and new_pass
    public $data;






    // -----------------------------------------------------------
    // __construct
    // -----------------------------------------------------------
    //
    // Keeps the address and the password for build.
    //
    // Used by:
    //   - Admins\UsersController and Users\RegisterController
    // -----------------------------------------------------------

    public function __construct($data)
    {
        $this->data = $data;
    }






    // -----------------------------------------------------------
    // build
    // -----------------------------------------------------------
    //
    // The message: the usernewpass view with the address and
    // the password, under the subject "New Password - VU KNF
    // Lab".
    //
    // Used by:
    //   - Laravel's mailer, when the mail is sent
    // -----------------------------------------------------------

    public function build()
    {
        return $this->view('users.mail.usernewpass')->subject("New Password - VU KNF Lab")->with([ 'email'=>$this->data['email'], 'new_pass'=>$this->data['new_pass'] ]);
    }
}
