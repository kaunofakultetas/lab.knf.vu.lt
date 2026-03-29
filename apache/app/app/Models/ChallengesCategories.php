<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChallengesCategories extends Model
{
    protected $table = "cyber_challenges_categories";
    public $timestamps = false;
    protected $rememberTokenName = false;
    
    protected $fillable = [
        'challenge_id', 'cat_id' 
    ];
    
    protected $hidden = [
    ];
    
    protected $casts = [
        'challenge_id'=>'integer',
        'cat_id'=>'integer',
    ];
}