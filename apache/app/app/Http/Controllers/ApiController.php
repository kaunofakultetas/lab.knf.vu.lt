<?php 

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Categories;
use App\Models\Challenges;
use App\Models\ChallengesHistory;
use App\Models\ChallengesSubmitted;
use App\Models\Groups;
use App\Models\GroupsUsers;
use App\Models\GroupsChallenges;

class ApiController extends Controller
{
    
    public function get_users(){
        
        return User::select(['id','vu_id','username','points'])->get()->toArray();
        
    }
    
    public function get_categories(){
        
        return Categories::select(['id','name'])->get()->toArray();
        
    }
    
    public function get_challenges(){
        
        return Challenges::select(['id','name','points','hidden'])->get()->toArray();
        
    }
    
    public function get_challenges_history(){
        
        return ChallengesHistory::select(['id','created_time','created','user_id','challenge_id'])->limit(50)->get()->toArray();
        
    }
    
    public function get_challenges_submitted(){
        
        return ChallengesSubmitted::select(['id','user_id','challenge_id','points_given','created'])->get()->toArray();
        
    }
    
    public function get_groups(){
        
        return Groups::select(['id','name','created'])->get()->toArray();
        
    }
    
    public function get_groups_users(){
        
        return GroupsUsers::select(['id','group_id','vu_id'])->get()->toArray();
        
    }
    
    public function get_groups_challenges(){
        
        return GroupsChallenges::select(['id','group_id','challenge_id'])->get()->toArray();
        
    }
    
    
}