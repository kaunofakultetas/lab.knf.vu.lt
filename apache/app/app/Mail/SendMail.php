<?php

// -----------------------------------------------------------
//  [*] SendMail — a mail template, sent as written
//
//  The mail of the control panel's mail templates: the
//  template's subject, with its content as the HTML body
//  exactly as the admin wrote it. Like every mail of the
//  app it is sent during the request, never queued.
//
//  Used by:
//    - Admins\EmailsController::sending_test — the test mail
//      to one address
//    - Admins\EmailsController::send — the mail to the
//      registered players' list
// -----------------------------------------------------------


namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;








// -----------------------------------------------------------
// SendMail
// -----------------------------------------------------------
//
//   __construct — takes the subject and the HTML
//   build       — the message
//
// Used by:
//   - Admins\EmailsController::sending_test and send
// -----------------------------------------------------------

class SendMail extends Mailable
{

    // The traits of Laravel's mailable skeleton
    use Queueable, SerializesModels;

    // The template's subject, which Mailable declares too
    public $subject;

    // The template's HTML
    public $content;






    // -----------------------------------------------------------
    // __construct
    // -----------------------------------------------------------
    //
    // Keeps the template's subject and HTML for build.
    //
    // Used by:
    //   - Admins\EmailsController::sending_test and send
    // -----------------------------------------------------------

    public function __construct(String $subject, String $content)
    {
        $this->subject = $subject;
        $this->content = $content;
    }






    // -----------------------------------------------------------
    // build
    // -----------------------------------------------------------
    //
    // The message: the subject, and the HTML as its body, with
    // no view around it.
    //
    // Used by:
    //   - Laravel's mailer, when the mail is sent
    // -----------------------------------------------------------

    public function build()
    {
        return $this->subject($this->subject)->html($this->content);
    }
}
