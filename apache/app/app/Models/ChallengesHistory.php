<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChallengesHistory extends Model
{

    protected $table = "cyber_challenges_history";

    public $timestamps = false;

    protected $rememberTokenName = false;

    protected $fillable = [
        'created_time',
        'created',
        'user_id',
        'challenge_id'
    ];

    protected $hidden = [];

    protected $casts = [
        'user_id'=>'integer',
        'challenge_id'=>'integer',
        'created_time' => 'integer',
        'created' => 'datetime'
    ];
}