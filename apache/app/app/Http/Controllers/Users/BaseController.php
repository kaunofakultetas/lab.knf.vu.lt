<?php
namespace App\Http\Controllers\Users;

use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request; 
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth; 
use App\Models\Settings;
use App\Models\ChallengesHistory;
use App\Models\User; 
use App\Models\Pages;
use App\Models\Groups;
use App\Models\GroupsChallenges;
use App\Models\GroupsUsers;
use View; 

class BaseController extends Controller
{

    public function __construct()
    {
       // $this->middleware('auth:users');

        $this->middleware(function ($request, $next) {
       
            $ranking_table = json_decode(Redis::get('ranking_table') ,1);
       
            if(isset($ranking_table[Auth::guard('users')->id()]['user_rank'])){
                
                $rank = $ranking_table[Auth::guard('users')->id()]['user_rank'];
                
                $my_position = SettingsController::ordinal($rank);
                $my_points = $ranking_table[Auth::guard('users')->id()]['points'];
                $username = User::find(Auth::guard('users')->id())->username; 
                
            } else {
                
                $my_position = NULL;
                $my_points = NULL;
                $username = NULL; 
            }
            
            $vu_id = User::where(['id'=>Auth::guard('users')->id()])->get()->pluck('vu_id')->first();
            
            $group_list = GroupsUsers::
            selectraw("cyber_users_groups.id as id, cyber_users_groups.group_id as group_id, cyber_groups.name, cyber_groups.public_id")->
            leftJoin("cyber_groups", "cyber_groups.id", "cyber_users_groups.group_id")->
            where(['cyber_users_groups.vu_id'=>$vu_id])->
            groupBy("cyber_users_groups.group_id")->
            get()->toArray();
           
            $task_history = json_decode(Redis::get('tasks_history'), 1);
            
            if(!is_array($task_history)){ $task_history = array(); }
            
            if(count($task_history)>0){
                foreach ($task_history as $kk => $vv) {
                    $task_history[$kk]['elapsed'] = SettingsController::time_elapsed_string($vv['created']);
                }
            } 
             
            $dates = explode(" - ",Settings::find(1)->val1);
             
            View::share('event_end', date("Y-m-d H:i:s", strtotime($dates[1])-7200));
            View::share('event_start', date("Y-m-d H:i:s", strtotime($dates[0])-7200));
            View::share('event_end_time', strtotime($dates[1]));
            View::share('event_start_time', strtotime($dates[0]));
            View::share('my_position', $my_position);
            View::share('history_list', $task_history);
            View::share('group_list', $group_list); 
            View::share('points', $my_points);
            View::share('username', $username);
            View::share('vu_id', $vu_id); 
             
            
            return $next($request);
            
            
            
            
        });
    }
}