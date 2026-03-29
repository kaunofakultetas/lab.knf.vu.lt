<?php

namespace App\Http\Controllers\Admins;

use Mail; 
use Session;
use App\Http\Controllers\Admins\BaseController;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth; 
use Illuminate\Http\Request; 
use Illuminate\Support\Str;
use App\Models\Challenges;
use App\Models\User;
use App\Models\Groups;
use App\Models\GroupsChallenges;
use App\Models\GroupsUsers;

class UsersGroupsController extends BaseController
{
    
    public function __construct()
    {
        parent::__construct();
    }
    
    public function index(Request $request)
    {
        
        if(Session::get('main') == 1){
            
            $all_groups = Groups::all()->toArray();
            $groups_ordered = Groups::all()->sortByDesc('id')->toArray();
            
        } else {
            
            $all_groups = Groups::where(['created_by'=>Auth::guard('admins')->id()])->get()->toArray();
            $groups_ordered = Groups::where(['created_by'=>Auth::guard('admins')->id()])->get()->sortByDesc('id')->toArray();
            
        }
        
        if (count($all_groups) > 0) {
            
            $arr = $groups_ordered;
        } else {
            
            $arr = array();
        }
        
        if (empty($request->get('page')) || ! is_numeric($request->get('page'))) {
            
            $curr_page = 1;
        } else {
            
            $curr_page = $request->get('page');
        }
        
        $perPage = 50;
        
        $currentItems = array_slice($arr, $perPage * ($curr_page - 1), $perPage);
        
        $paginator = new LengthAwarePaginator($currentItems, count($arr), $perPage, $curr_page, [
            'path' => url('control-panel/users/vu-groups')
        ]);
        
        if (count($arr) == 0) {
            
            $arr = array();
            $paginator = array();
        }
        
        return view('admins.users-groups', [
            'recordlist' => $paginator
        ]);
    }
    
    public function create()
    {
        
        return view('admins.dialogs.users_groups_form', [
            'type'=>'new'
        ]);
        
    }
    
    public function store(Request $request)
    {
        Groups::create(['name' => $request->get('name'), 'created_by'=>Auth::guard('admins')->id(), 'created'=>date("Y-m-d H:i:s"), 'public_id'=>Str::uuid()]);
        
        session()->flash('success', 'Created!');
        
        return back();
    }
    
    public function edit(Request $request, $uid)
    {
        return view('admins.dialogs.users_groups_form', [
            'name' => Groups::find($uid)->name,
            'type'=>'edit',
            'uid'=>$uid
        ]);
    }
    
    public function update(Request $request)
    {
        Groups::where(['id' => $request->get('uid')])->update(['name' => $request->get('name')]);
        
        session()->flash('success', 'Updated!');
        
        return back();
        
    }

    public function destroy($uid)
    {
        session()->flash('success', 'Deleted!');
        
        GroupsChallenges::where(['group_id'=>$uid])->delete();
        GroupsUsers::where(['group_id'=>$uid])->delete();
        Groups::destroy($uid);
        
        return back();
        
    }

    public function users_list(Request $request, $uid)
    {
        if (count(GroupsUsers::where(['group_id'=>$uid])->get()->toArray()) > 0) {
            
            $arr = GroupsUsers::where(['cyber_users_groups.group_id'=>$uid])  
            ->get()->sortByDesc('cyber_users_groups.id')->toArray();
        } else {
            
            $arr = array();
        }
        
        if (empty($request->get('page')) || ! is_numeric($request->get('page'))) {
            
            $curr_page = 1;
        } else {
            
            $curr_page = $request->get('page');
        }
        
        $perPage = 50;
        
        $currentItems = array_slice($arr, $perPage * ($curr_page - 1), $perPage);
        
        $paginator = new LengthAwarePaginator($currentItems, count($arr), $perPage, $curr_page, [
            'path' => url('control-panel/users/vu-groups/users_list/'.$uid)
        ]);
        
        if (count($arr) == 0) {
            
            $arr = array();
            $paginator = array();
        }
        
        return view('admins.users-groups-userlist', [
            'recordlist' => $paginator,
            'group_name' => Groups::find($uid)->name,
            'uid'=>$uid
        ]);
    }
    
    public function togroup_form(Request $request, $type, $uid)
    {
        
        return view('admins.dialogs.users_togroups_form', [
            'type'=>$type,
            'uid'=>$uid
        ]);
        
    }

    public function users_store(Request $request)
    {
        $num = GroupsUsers::where(['vu_id' => $request->get('vu_id'), 'group_id' => $request->get('uid')])->get()->count();
        
        if($num == 0){
            GroupsUsers::create(['vu_id' => $request->get('vu_id'), 'group_id' => $request->get('uid')]); 
            session()->flash('success', 'Added!');
        } else {
            session()->flash('danger', 'User already added!');
        }
        
        return back();
    }

    public function group_destroy($uid)
    {
        session()->flash('success', 'Deleted!');
         
        GroupsUsers::destroy($uid);
        
        return back();
        
    }

    public function challenges_list(Request $request, $uid)
    {
        if (count(GroupsChallenges::where(['group_id'=>$uid])->get()->toArray()) > 0) {
            
            $arr = GroupsChallenges::
            selectraw("cyber_groups_challenges.id as id, cyber_challenges.name as name")->
            leftJoin("cyber_challenges", "cyber_challenges.id", "cyber_groups_challenges.challenge_id")->
            where(['group_id'=>$uid])->
            groupBy("cyber_challenges.id")->
            get()->sortByDesc('id')->toArray();
            
            
        } else {
            
            $arr = array();
        }
        
        if (empty($request->get('page')) || ! is_numeric($request->get('page'))) {
            
            $curr_page = 1;
        } else {
            
            $curr_page = $request->get('page');
        }
        
        $perPage = 50;
        
        $currentItems = array_slice($arr, $perPage * ($curr_page - 1), $perPage);
        
        $paginator = new LengthAwarePaginator($currentItems, count($arr), $perPage, $curr_page, [
            'path' => url('control-panel/users/vu-groups/challenges_list/'.$uid)
        ]);
        
        if (count($arr) == 0) {
            
            $arr = array();
            $paginator = array();
        }
        
        return view('admins.users-groups-challengeslist', [
            'recordlist' => $paginator,
            'group_name' => Groups::find($uid)->name,
            'uid'=>$uid
        ]);
    }

    public function challenge_destroy($uid)
    {
        session()->flash('success', 'Deleted!');
        
        GroupsChallenges::destroy($uid);
        
        return back();
        
    }

    public function challenges_togroup_form(Request $request, $type, $uid)
    {
        
        $already_selected = GroupsChallenges::where(["group_id"=>$uid])->get()->pluck('challenge_id')->toArray();
     
        $challenge_list = Challenges::wherenotin("id", $already_selected)->get()->pluck('name', 'id')->toArray();
        
        return view('admins.dialogs.users_challengestogroups_form', [
            'type'=>$type,
            'uid'=>$uid,
            'challenge_list'=>$challenge_list
        ]);
        
    }

    public function challenges_store(Request $request)
    {
        $num = GroupsChallenges::where(['group_id' => $request->get('uid'), 'challenge_id' => $request->get('challenge_id')])->get()->count();
        
        if($num == 0){
            GroupsChallenges::create(['challenge_id' => $request->get('challenge_id'), 'group_id' => $request->get('uid')]);
            session()->flash('success', 'Added!');
        } else {
            session()->flash('danger', 'Challenge already added!');
        }
        
        return back();
    }

    public function group_leaderboard(Request $request, $uid){
         
        $group_id = $uid;
        
        $challenges = GroupsChallenges::select("challenge_id")->where(["group_id"=>$group_id])->get()->pluck("challenge_id")->toArray();
        
        $ranking_table = json_decode(json_encode(UsersGroupsController::get_group_leaderboard($challenges)), 1);
        
        $ordinalUsers = collect($ranking_table)->map(function($user) {
            $user['position'] = SettingsController::ordinal($user['position']);
            return $user;
        });
            
            $ordinalUsers = collect($ordinalUsers)->map(function($user) {
                $user['last_challenge'] = SettingsController::time_elapsed_string($user['last_challenge']);
                return $user;
            });
                
                return view('admins.groups_leaderboard', [
                    'ranking_table'=>$ordinalUsers,
                    'group_name'=>Groups::select("name")->where(['id'=>$group_id])->get()->pluck('name')->first(),
                    'group_id'=>$group_id
                ]);
        
    }
 
    public static function get_group_leaderboard($challenges){
        
        $data = DB::table('cyber_users')
        ->leftJoin('cyber_challenges_submitted', 'cyber_challenges_submitted.user_id', '=', 'cyber_users.id')
        ->whereIn('cyber_challenges_submitted.challenge_id', $challenges)
        ->groupBy('cyber_users.id')
        ->orderByDesc('cyber_users.points')
        ->orderBy('cyber_challenges_submitted.created')
        ->selectRaw('row_number() OVER (ORDER BY `cyber_users`.`points` DESC) AS `position`, `cyber_users`.`id` AS `id`, `cyber_users`.`points` AS `points`, `cyber_users`.`email` AS `email`, `cyber_users`.`username` AS `username`, `cyber_users`.`user_type` AS `user_type`, `cyber_challenges_submitted`.`created` AS `last_challenge`')
        ->get()->toArray();
        
        return $data;
        
    }







}