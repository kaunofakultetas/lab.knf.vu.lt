<?php

// -----------------------------------------------------------
//  [*] SendRemindLink — the password-reminder link
//
//  The mail of the forgotten-password form: a link to
//  /reset/{token} on APP_URL, good for an hour, which opens
//  a page whose button sets and mails a new password. The
//  view is users.mail.userpassreset.
//
//  Used by:
//    - Users\RegisterController::forgotpassword
// -----------------------------------------------------------


namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;








// -----------------------------------------------------------
// SendRemindLink
// -----------------------------------------------------------
//
//   __construct — takes the token
//   build       — the message with its link
//
// Used by:
//   - Users\RegisterController::forgotpassword
// -----------------------------------------------------------

class SendRemindLink extends Mailable
{

    // The traits of Laravel's mailable skeleton
    use Queueable, SerializesModels;

    // The reminder token, under the key uid
    public $data;






    // -----------------------------------------------------------
    // __construct
    // -----------------------------------------------------------
    //
    // Keeps the token for build.
    //
    // Used by:
    //   - Users\RegisterController::forgotpassword
    // -----------------------------------------------------------

    public function __construct($data)
    {
        $this->data = $data;
    }






    // -----------------------------------------------------------
    // build
    // -----------------------------------------------------------
    //
    // The message: the userpassreset view with the link, under
    // the subject "Reset Password - VU KNF Lab". The link is
    // built from the APP_URL environment variable, not from
    // the request, so a forged Host header cannot redirect it.
    //
    // Used by:
    //   - Laravel's mailer, when the mail is sent
    // -----------------------------------------------------------

    public function build()
    {
        $url = env('APP_URL').'/reset/'.$this->data['uid'];

        return $this->view('users.mail.userpassreset')->subject("Reset Password - VU KNF Lab")->with([ 'link_url'=>$url ]);
    }
}
