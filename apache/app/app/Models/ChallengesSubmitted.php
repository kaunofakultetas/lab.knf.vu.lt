<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChallengesSubmitted extends Model
{
    protected $table = "cyber_challenges_submitted";
    public $timestamps = false;
    protected $rememberTokenName = false;
    
    protected $fillable = [
        'user_id', 'challenge_id', 'points_given', 'created', 'ip'
    ];
    
    protected $hidden = [
    ];
    
    protected $casts = [
        'challenge_id'=>'integer',
        'task_id'=>'integer',
        'points_given'=>'integer',
        'created'=>'datetime',
        'ip'=>'string'
    ];
}