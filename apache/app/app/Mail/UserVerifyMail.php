<?php

// -----------------------------------------------------------
//  [*] UserVerifyMail — the account verification link
//
//  The mail a new local account gets at registration: a
//  link to /verify/{token} on APP_URL, which activates the
//  account once opened. The view is users.mail.verification.
//
//  Used by:
//    - Users\RegisterController::store
// -----------------------------------------------------------


namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;








// -----------------------------------------------------------
// UserVerifyMail
// -----------------------------------------------------------
//
//   __construct — takes the token
//   build       — the message with its link
//
// Used by:
//   - Users\RegisterController::store
// -----------------------------------------------------------

class UserVerifyMail extends Mailable
{

    // The traits of Laravel's mailable skeleton
    use Queueable, SerializesModels;

    // The verification token, under the key uid
    public $data;






    // -----------------------------------------------------------
    // __construct
    // -----------------------------------------------------------
    //
    // Keeps the token for build.
    //
    // Used by:
    //   - Users\RegisterController::store
    // -----------------------------------------------------------

    public function __construct($data)
    {
        $this->data = $data;
    }






    // -----------------------------------------------------------
    // build
    // -----------------------------------------------------------
    //
    // The message: the verification view with the link, under
    // the subject "Verify your VU KNF Lab account". The link is
    // built from the APP_URL environment variable, not from
    // the request.
    //
    // Used by:
    //   - Laravel's mailer, when the mail is sent
    // -----------------------------------------------------------

    public function build()
    {
        $url = env('APP_URL').'/verify/'.$this->data['uid'];

        return $this->view('users.mail.verification')
            ->subject("Verify your VU KNF Lab account")
            ->with([ 'link_url'=>$url ]);
    }
}
