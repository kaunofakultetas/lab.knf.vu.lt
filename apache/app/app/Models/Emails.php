<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Emails extends Model
{

    protected $table = "cyber_emails";

    public $timestamps = false;

    protected $rememberTokenName = false;

    protected $fillable = [
        'name',
        'subject',
        'content',
        'sent'
    ];
}