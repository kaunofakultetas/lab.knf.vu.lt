<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChallengesFiles extends Model
{
    protected $table = "cyber_challenges_files";
    public $timestamps = false;
    protected $rememberTokenName = false;
    
    protected $fillable = [
        'challenge_id', 'name', 'file_url'
    ];
    
    protected $hidden = [
    ];
    
    protected $casts = [
        'challenge_id'=>'integer',
        'name'=>'string', 
        'file_url'=>'string'
    ];
    
    public static function getFilesById($id){
        
        $files = ChallengesFiles::where('challenge_id', $id)->get()->toArray();
        
        return $files;
        
    }
}