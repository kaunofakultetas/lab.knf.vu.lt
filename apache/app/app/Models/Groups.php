<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Models\Challenges;
use App\Models\ChallengesSubmitted;
use App\Models\User;
use App\Models\Groups;
use App\Models\GroupsChallenges;
use App\Models\GroupsUsers; 

class Groups extends Model
{

    protected $table = "cyber_groups";

    public $timestamps = false;

    protected $rememberTokenName = false; 

    protected $fillable = [
        'id',
        'public_id',
        'created_by',
        'name', 
        'created'
    ];

    protected $hidden = [];
 
    protected $casts = [
        'id' => 'integer',
        'created_by' => 'integer',
        'public_id' => 'string',
        'name' => 'string',
        'created' => 'datetime'
    ];
    
    public static function groupPointsPercent($group_id){
         
        $user_id = Auth::guard('users')->id(); 
        $group_challenges_arr = GroupsChallenges::where(['group_id'=>$group_id])->get()->pluck("challenge_id")->toArray(); 
        $solved_challenges = ChallengesSubmitted::where(['user_id'=>$user_id])->whereIn("challenge_id", $group_challenges_arr)->get()->pluck('challenge_id')->toArray();
        
        $sum_submitedpoints = ChallengesSubmitted::selectraw("SUM(points_given) as total_points")
        ->where(['user_id'=>$user_id])
        ->whereIn("challenge_id", $solved_challenges)
        ->get()->pluck('total_points')->first();
        
        $sum_totalpoints = Challenges::selectraw("SUM(points) as total_points") 
        ->whereIn("id", $group_challenges_arr)
        ->get()->pluck('total_points')->first();
        
        if($sum_totalpoints !=0 && $sum_totalpoints != ''){
            $percentage = $sum_submitedpoints * 100 / $sum_totalpoints;
        } else {
            $percentage = 0;
        }
        return round($percentage);
        
        
    }
    
    public static function groupExists($public_id){
        
        $num = Groups::where(['public_id'=>$public_id])->count();
        
        if($num == 1){
            return true;
        } else {
            return false;
        }
        
        
    }
    
    
    
    
    
    
    
    
    
}