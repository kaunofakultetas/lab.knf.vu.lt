<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendNewPass extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function build()
    {

        return $this->view('users.mail.usernewpass')->subject("New Password - VU KNF Lab")->with([ 'email'=>$this->data['email'], 'new_pass'=>$this->data['new_pass'] ]);
    }
}