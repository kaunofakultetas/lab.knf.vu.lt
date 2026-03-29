<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendRemindLink extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function build()
    {


        $url = env('APP_URL').'/reset/'.$this->data['uid'];

        return $this->view('users.mail.userpassreset')->subject("Reset Password - VU KNF Lab")->with([ 'link_url'=>$url ]);
    }
}