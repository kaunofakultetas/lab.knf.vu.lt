<?php

namespace App\Http\Controllers\Admins;

use Session;
use App\Http\Controllers\Admins\BaseController;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\Partners;
use App\Models\Categories;
use App\Models\Challenges;
use App\Models\ChallengesHistory;
use App\Models\ChallengesFiles;
use App\Models\ChallengesCategories;
use App\Models\ChallengesSubmitted;
use App\Models\GroupsChallenges;

class ChallengesController extends BaseController
{
    
    public function __construct()
    {
        parent::__construct();
    }
  
    public function index(Request $request)
    { 
        
        if(empty($request->query('q'))){
            $challenges = Challenges::all()->toArray();
            $challenges_sort = Challenges::all()->sortByDesc('id')->toArray();
        } else {
            $challenges = Challenges::where('name', 'like', '%'.$request->query('q').'%')->orWhere('descr', 'like', '%'.$request->query('q').'%')->orWhere('content', 'like', '%'.$request->query('q').'%')->get()->toArray();
            $challenges_sort = Challenges::where('name', 'like', '%'.$request->query('q').'%')->orWhere('descr', 'like', '%'.$request->query('q').'%')->orWhere('content', 'like', '%'.$request->query('q').'%')->get()->sortByDesc('id')->toArray();
        }
        
        if (count($challenges) > 0) {
            
            $arr = $challenges_sort;
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
            'path' => url('control-panel/challenges')
        ]);
        
        $paginator = $paginator->appends('q', $request->query('q'));
        
        if (count($arr) == 0) { 
            
            $arr = array();
            $paginator = array();
        }
        return view('admins.challenges', [
            'recordlist' => $paginator,
            'user_id'=>Auth::guard('admins')->id()
        ]);
    }
 
    public function create() 
    { 
        return view('admins.challenges_form', [
            'url' => env('APP_URL') . '/control-panel/challenges/store',
            'partner_list' => Partners::all()->pluck('name', 'id')->toArray(),
            'partner_id'=>'',
            'name' => '',
            'descr' => '',
            'content' => '',
            'answer' => '',
            'points' => '',
            'uid'=>''
        ]);
    }
 
    public function store(Request $request)
    { 
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'partner_id' => 'required',
            'descr' => 'required',
            'content' => 'required',
            'answer' => 'required',
            'points' => 'required'
        ]);
        
        if(Session::get('main') == 1){
            $hidden=0;
        } else {
            $hidden=1;
        }
        
        Challenges::create([
            'name' => $request->get('name'),
            'public_id'=>Str::uuid(),
            'partner_id' => $request->get('partner_id'),
            'created_by'=>Auth::guard('admins')->id(),
            'descr' => $request->get('descr'),
            'content' => $request->get('content'),
            'answer' => trim($request->get('answer')),
            'points' => $request->get('points'),
            'hidden' => $hidden
        ]);
        
        session()->flash('success', 'Created!');
        
        return redirect(route('admin.challenges'));
        
    } 
 
    public function edit(Request $request, $uid)
    { 
        return view('admins.challenges_form', [
            'url' => env('APP_URL') . '/control-panel/challenges/update',
            'partner_list' => Partners::all()->pluck('name', 'id')->toArray(),
            'partner_id'=>Challenges::find($uid)->partner_id,
            'name' => Challenges::find($uid)->name,
            'descr' => Challenges::find($uid)->descr,
            'content' => Challenges::find($uid)->content,
            'answer' => Challenges::find($uid)->answer, 
            'points' => Challenges::find($uid)->points,
            'uid' => $uid
        ]);
    }
 
    public function update(Request $request)
    { 
        Challenges::where(['id' => $request->get('uid')])->update([
            'name' => $request->get('name'),
            'partner_id' => $request->get('partner_id'),
            'created_by' => Auth::guard('admins')->id(),
            'descr' => $request->get('descr'),
            'content' => $request->get('content'),
            'answer' => $request->get('answer'),
            'points' => $request->get('points')
        ]);
        
        session()->flash('success', 'Updated!');
        
        return redirect(route('admin.challenges'));
    }
 
    public function destroy($uid)
    {
        
        Challenges::destroy($uid);
        ChallengesHistory::where(['challenge_id'=>$uid])->delete();
        ChallengesFiles::where(['challenge_id'=>$uid])->delete();
        ChallengesSubmitted::where(['challenge_id'=>$uid])->delete();
        ChallengesCategories::where(['challenge_id'=>$uid])->delete();
        GroupsChallenges::where(['challenge_id'=>$uid])->delete();
        
        session()->flash('success', 'Deleted!');
        
        return back();
    }

    public function categories(Request $request, $uid){
      
            return view('admins.dialogs.challenges_categories', [
                'url' => env('APP_URL') . '/control-panel/challenges/categories/store',
                'categories_list' => Categories::all()->pluck('name', 'id')->toArray(), 
                'selected_categories'=> ChallengesCategories::where(['challenge_id'=>$uid])->get()->pluck('cat_id', 'id')->toArray(),
                'challenge_name'=> Challenges::find($uid)->name,
                'uid'=>$uid
            ]); 
        
    }
    
    public function categories_store(Request $request){
        
        ChallengesCategories::where(['challenge_id'=>$request->get('uid')])->delete();
        
        $items = $request->get('item');
        
        if(count($items)>0){
         
            foreach($items as $k => $v){
                
                ChallengesCategories::create([
                    'cat_id' => $k,
                    'challenge_id' => $request->get('uid') 
                ]);
                
            }
        
        session()->flash('success', 'Categoried updated!');
        
        }
        
        return back();
        
    }
    
    public function file_list(Request $request, $uid)
    {
        if (count(ChallengesFiles::where(['challenge_id'=>$uid])->get()->toArray()) > 0) {
            
            $arr = ChallengesFiles::where(['challenge_id'=>$uid])->get()->toArray();
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
            'path' => url('control-panel/challenges/files/'.$uid)
        ]);
        
        if (count($arr) == 0) {
            
            $arr = array();
            $paginator = array();
        }
        return view('admins.challenges_files', [
            'challenge_name'=>Challenges::find($uid)->name,
            'recordlist' => $paginator,
            'uid'=>$uid
        ]);
    }
    
    public function file_form(Request $request, $type, $uid){
        
        if($type =='new'){
            $url = env('APP_URL')."/control-panel/challenges_file/store";
            $name = '';
            $file_url = '';
        } elseif($type =='edit'){
            $url = env('APP_URL')."/control-panel/challenges_file/update";
            $name = ChallengesFiles::find($uid)->name;
            $file_url = ChallengesFiles::find($uid)->file_url;
        }
        
        return view('admins.dialogs.challenge_files_form', [ 
            'type'=>$type,
            'uid' =>$uid,
            'url'=>$url,
            'name'=>$name,
            'file_url'=>$file_url
        ]);
    }
    
    public function file_store(Request $request){
        
        ChallengesFiles::create([
            'challenge_id'=>$request->get('uid'),
            'name'=>$request->get('name'),
            'file_url'=>$request->get('file_url')
        ]);
        
        session()->flash('success', 'File created!');
        
        return back();
    }
    
    public function file_update(Request $request){
        
        ChallengesFiles::where(['id'=>$request->get('uid')])->update([
            'name'=>$request->get('name'),
            'file_url'=>$request->get('file_url')]);
        
        session()->flash('success', 'File updated!');
        
        return back();
    }
    
    public function file_destroy($uid)
    {
        
        ChallengesFiles::destroy($uid); 
        
        session()->flash('success', 'File deleted!');
        
        return back();
    }
    
    public function status(Request $request, $status, $challenge_id){
        
        Challenges::where(['id'=>$challenge_id])->update(['hidden'=>$status]);
        
        return back();
    }
    
    
    
    
    
    
    
    
    
    
}
