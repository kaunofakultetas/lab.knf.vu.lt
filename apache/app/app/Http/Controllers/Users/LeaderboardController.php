<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Users\BaseController;
use App\Http\Controllers\Users\SettingsController; 
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth; 
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Partners;
use App\Models\Categories;
use App\Models\Challenges;
use App\Models\ChallengesHistory;
use App\Models\ChallengesFiles;
use App\Models\ChallengesCategories;
use App\Models\ChallengesSubmitted;

class LeaderboardController extends BaseController
{
  
    public function __construct()
    {
        parent::__construct();
    }
    
    public function index(Request $request)
    {
        
        $ranking_table = json_decode(Redis::get('ranking_table') ,1);
         
        $ordinalUsers = collect($ranking_table)->map(function($user) {
            $user['user_rank'] = SettingsController::ordinal($user['user_rank']);
            return $user;
        });
        
            $ordinalUsers = collect($ordinalUsers)->map(function($user) {
                $user['last_challenge'] = SettingsController::time_elapsed_string($user['last_challenge']);
                return $user;
            }); 
 
            return view('users.leaderboard', ['ranking_table'=>$ordinalUsers]);
    }  
    
}