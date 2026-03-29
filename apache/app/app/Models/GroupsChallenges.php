<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupsChallenges extends Model
{

    protected $table = "cyber_groups_challenges";

    public $timestamps = false;

    protected $rememberTokenName = false;

    protected $fillable = [
        'id',
        'group_id',
        'challenge_id'
    ];

    protected $hidden = [];

    protected $casts = [
        'id' => 'integer',
        'group_id' => 'integer',
        'challenge_id' => 'integer'
    ];
}