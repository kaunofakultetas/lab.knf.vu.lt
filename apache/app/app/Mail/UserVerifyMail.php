<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserVerifyMail extends Mailable
{ 
    use Queueable, SerializesModels;
    
    public $data;
    
    public function __construct($data)
    {
        $this->data = $data;
    }
    
    public function build()
    {
        
        $url = env('APP_URL').'/verify/'.$this->data['uid'];
        
        return $this->view('users.mail.verification')
        ->subject("Verify your VU KNF Lab account")
        ->with([ 'link_url'=>$url ]);
    }
    
}