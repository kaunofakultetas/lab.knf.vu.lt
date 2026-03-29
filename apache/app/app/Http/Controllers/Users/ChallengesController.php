<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Users\BaseController;
use App\Http\Controllers\Users\SettingsController;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\Groups;
use App\Models\User;
use App\Models\Feedback;
use App\Models\Partners;
use App\Models\Categories;
use App\Models\Challenges;
use App\Models\ChallengesHistory;
use App\Models\ChallengesFiles;
use App\Models\ChallengesCategories;
use App\Models\ChallengesSubmitted;

class ChallengesController extends BaseController
{
  
    public function __construct()
    {
        parent::__construct();
    }
    
    public function index(Request $request)
    { 
        
        if (count(Challenges::where(['hidden'=>0])->orderby('points')->get()->toArray()) > 0) {
            
            $arr = Challenges::where(['hidden'=>0])->orderby('points')->get()->toArray();
              
            foreach ($arr as $k => $v) {
                $arr[$k]['public_id'] = $v['public_id'];
                
                $arr[$k]['solved'] = ChallengesSubmitted::where('user_id', '=', Auth::guard('users')->id())->where('challenge_id', '=', $v['id'])->count();
                
                $arr[$k]['categories'] = implode(", ", ChallengesCategories::
                where(['challenge_id' => $arr[$k]['id']])
                ->leftJoin('cyber_categories', 'cyber_categories.id', '=', 'cyber_challenges_categories.cat_id')
                ->get()->pluck('name')->toArray());
                
                $arr[$k]['categories_ids'] = implode(" c", ChallengesCategories::
                    where(['challenge_id' => $arr[$k]['id']])
                    ->leftJoin('cyber_categories', 'cyber_categories.id', '=', 'cyber_challenges_categories.cat_id')
                    ->get()->pluck('id')->toArray());
                
                $arr[$k]['thumbs_up_count'] = Redis::get('ThumbsUp_'.$v['public_id']) ?? 0;
                $arr[$k]['thumbs_down_count'] = Redis::get('ThumbsDown_'.$v['public_id']) ?? 0;
                $arr[$k]['voted'] = Redis::get('Vote_'.$v['public_id'].'_'.Auth::guard('users')->id()) ?? 0;
                
            }
        } else {
            
            $arr = array();
        } 
        
        function aasort(&$array, $key)
        {
            $sorter = array();
            $ret = array();
            reset($array);
            foreach ($array as $ii => $va) {
                $sorter[$ii] = $va[$key];
            }
            asort($sorter);
            foreach ($sorter as $ii => $va) {
                $ret[$ii] = $array[$ii];
            }
            $array = $ret;
        }
        
        aasort($arr, "solved");
       
        return view('users.dashboard', [
            'recordlist' => $arr,
            'categories' => Categories::all()->pluck('name', 'id')->toArray()
        ]);
    }
    
    public function show(Request $request, $public_id, $public_group_id=NULL){
        
        $validator = Validator::make(['public_id'=>$public_id], [
            'public_id' => ['required', 'exists:cyber_challenges,public_id'] 
         ]);
        
        if ($validator->fails()) { 
             return redirect(route('user_dashboard'))->withErrors(['Challenge doesn\'t exists!']);
        }
        
        if($public_group_id != NULL){
            if(Groups::groupExists($public_group_id) == false){
                return back()->withErrors(['Error!']);
            }
        }
        
        $files = ChallengesFiles::getFilesById(Challenges::getIdByPublicId($public_id));
   
        return view('users.challenge', [
            'data'=>Challenges::where(['public_id'=>$public_id])->get()->first(),
            'partner'=>Partners::where(['id'=>Challenges::getPartnerIdByPublicId($public_id)])->get()->first(),
            'files'=>$files,
            'public_id' => $public_id,
            'public_group_id' => $public_group_id,
            'thumbs_up_count'=>Redis::get('ThumbsUp_'.$public_id) ?? 0,
            'thumbs_down_count'=>Redis::get('ThumbsDown_'.$public_id) ?? 0,
            'voted'=>Redis::get('Vote_'.$public_id.'_'.Auth::guard('users')->id()) ?? 0
        ]);
        
    }
    
    public function submit_answer(Request $request, $public_id, $public_group_id=NULL){
        
        $validator = Validator::make(['public_id'=>$public_id], [
            'public_id' => ['required', 'exists:cyber_challenges,public_id']
        ]);
        
        if ($validator->fails()) {
            return redirect(route('user_dashboard'))->withErrors(['Challenge doesn\'t exists!']);
        }
        
        if($public_group_id != NULL){
            if(Groups::groupExists($public_group_id) == false){
                return back()->withErrors(['Error!']);
            }
        }
        
        $answer = trim(str_replace("VU{","",str_replace("}","",$request->get('answer'))));
        
        $challenge_id = Challenges::getIdByPublicId($public_id);
        
        $challenge_answer = Challenges::where(['public_id' => $public_id])->get()->pluck('answer')->first();
        
        $challenge_points = Challenges::where(['public_id' => $public_id])->get()->pluck('points')->first();
        
        $solved = ChallengesSubmitted::where('user_id', '=', Auth::guard('users')->id())->where('challenge_id', '=', $challenge_id)->count();
        
        if ($solved == 0) {
            if (trim($challenge_answer) == $answer) {
                   
                $submitTask = new ChallengesSubmitted();
                $submitTask->user_id = Auth::guard('users')->id();
                $submitTask->challenge_id = $challenge_id;
                $submitTask->points_given = $challenge_points;
                $submitTask->created = date('Y-m-d H:i:s');
                $submitTask->ip = SettingsController::getUserIP();
                $submitTask->save();
                  
                User::where(["id" => Auth::guard('users')->id()])->update(
                    ["points" => User::select('points')->where(
                        ["id" => Auth::guard('users')->id()])->get()->pluck('points')->first() + $challenge_points]);
                
                ChallengesHistory::create([
                    'user_id'=>Auth::guard('users')->id(),
                    'challenge_id'=>$challenge_id,
                    'content' => User::find(Auth::guard('users')->id())->getUsername() . ' solved ' . Challenges::find($challenge_id)->name . ' challenge',
                    'created_time' => time(),
                    'created' => date('Y-m-d H:i:s')
                ]);
                
                Redis::set('tasks_history', json_encode(
                    ChallengesHistory::selectRaw("cyber_challenges_history.*, cyber_users.username, cyber_challenges.name")
                    ->leftJoin('cyber_users', 'cyber_users.id', 'cyber_challenges_history.user_id')
                    ->leftJoin('cyber_challenges', 'cyber_challenges.id', 'cyber_challenges_history.challenge_id')
                    ->orderBy('id', 'desc')
                    ->take(6)
                    ->get()
                    ->toArray()));
                
                if($public_group_id == NULL){
                    return redirect()->intended(route('user_dashboard'))->withSuccess("Your answer is correct! You received $challenge_points points");
                } else {
                    return redirect('/user/group/'.$public_group_id)->withSuccess("Your answer is correct! You received $challenge_points points");
                }
                
            } else {
                 
                return back()->withErrors(['Wrong answer']);
                
            }
        } else {
            
            return back()->withErrors(['Already solved']);
        }
        
        
        
        
        
    }
       
    public function vote(Request $req, $type, $public_id){
        
        $voted = Redis::get('Vote_'.$public_id.'_'.Auth::guard('users')->id()) ?? 0;
        
        if($voted == 0){
          
            if($type == 'up'){
                
                $current = Redis::get('ThumbsUp_'.$public_id) ?? 0;
                
                Redis::set('ThumbsUp_'.$public_id, $current + 1);
                
            }
            
            if($type == 'down'){
                
                $current = Redis::get('ThumbsDown_'.$public_id) ?? 0;
                
                Redis::set('ThumbsDown_'.$public_id, $current + 1);
                
            } 
            
            Redis::set('Vote_'.$public_id.'_'.Auth::guard('users')->id(), 1);
        
        }
        
        
        
    }

    public function dialog_feedback(){
         
        return view('users.dialogs.feedback', []);
        
    }
    
    public function feedback_save(Request $req){
        
        Feedback::create([
            'user_id'=>Auth::guard('users')->id(),
            'feedback'=>$req->get('feedback'),
            'created'=>date("Y-m-d H:i:s")
        ]);
        
        return back()->withSuccess("Thank you for your feedback!");
    }
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    


}