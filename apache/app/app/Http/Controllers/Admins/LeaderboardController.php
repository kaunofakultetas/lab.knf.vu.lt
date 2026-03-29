<?php

namespace App\Http\Controllers\Admins;

use App\Http\Controllers\Admins\BaseController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Groups;
use App\Models\ChallengesSubmitted;
use App\Models\ChallengesHistory;
use Illuminate\Support\Facades\DB; 

class LeaderboardController extends BaseController
{
    
    public function __construct()
    {
        parent::__construct();
    }
    
    public function dashboard(Request $request)
    {
        
       // LeaderboardController::update_rank_table();
        
        $th = ChallengesHistory::selectRaw("cyber_challenges_history.*, cyber_users.username, cyber_challenges.name, cyber_challenges.points")
        ->leftJoin('cyber_users', 'cyber_users.id', 'cyber_challenges_history.user_id')
        ->leftJoin('cyber_challenges', 'cyber_challenges.id', 'cyber_challenges_history.challenge_id')
        ->orderBy('id', 'desc')
        ->take(15)
        ->get()
        ->toArray();
        
        if(!is_array($th)){ $th = array(); }
        
        $th = collect($th)->map(function($user) {
            $user['created'] = $this->time_elapsed_string($user['created']);
            return $user;
        });
        
        $ranking_table = json_decode(Redis::get('ranking_table') ,1);
        $rtt = json_decode(Redis::get('ranking_table') ,1);
        $ranking_table = array_slice($ranking_table, 0, 10, true);
        
        $arrr = [];
        
        foreach($rtt as $k => $v){
            
            $arrr['standings'][] = ['pos'=>$v['user_rank'], 'team'=>$v['username'], 'score'=>$v['points']];
            
        }
        
        //print_r(json_encode($arrr, JSON_UNESCAPED_UNICODE));
        
        $ordinalUsers = collect($ranking_table)->map(function($user) {
            $user['user_rank'] = $this->ordinal($user['user_rank']);
            return $user;
        });
            
        $ordinalUsers = collect($ordinalUsers)->map(function($user) {
            $user['last_challenge'] = $this->time_elapsed_string($user['last_challenge']);
            return $user;
        });
         
            return view('admins.dashboard', [
            'registered_users'=>User::all()->count(),
             'registered_teams'=>User::where(['user_type'=>2])->count(),
             'loggedin_users'=>User::whereNotNull('last_login')->count(),
             'solvedone_users'=>ChallengesSubmitted::distinct()->count('user_id'),
             'total_groups'=>Groups::all()->count(),
            'ranking_table'=>$ordinalUsers,
            'latest_solved'=>$th
        ]);
    }
    
    public static function ordinal($number) {
        $ends = array('th','st','nd','rd','th','th','th','th','th','th');
        if ((($number % 100) >= 11) && (($number%100) <= 13))
            return $number. 'th';
            else
                return $number. $ends[$number % 10];
    }
    
    public static function time_elapsed_string($datetime, $full = false)
    {
        $now = new \DateTime();
        $ago = new \DateTime($datetime);
        $diff = $now->diff($ago);
        
        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;
        
        $string = array(
            'y' => 'year',
            'm' => 'month',
            'w' => 'week',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second'
        );
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }
        
        if (! $full)
            $string = array_slice($string, 0, 1);
            return $string ? implode(', ', $string) . ' ago' : 'just now';
    }
    
    
    public static function update_rank_table(){
        
        $arr = DB::table('cyber_users_ranks')->get()->keyBy('user_id')->toArray();
        
        Redis::set('ranking_table', json_encode($arr));
        
    } 
    
}