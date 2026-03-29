<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{

    protected $table = "cyber_feedback";

    public $timestamps = false;

    protected $rememberTokenName = false;

    protected $fillable = [
        'user_id',
        'feedback',
        'created'
    ];
}