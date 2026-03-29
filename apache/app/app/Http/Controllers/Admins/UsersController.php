<?php

namespace App\Http\Controllers\Admins;

use Mail;
use App\Mail\SendNewPass;
use App\Http\Controllers\Admins\SettingsController; 
use App\Http\Controllers\Admins\BaseController;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use App\Models\GroupsUsers;
use App\Models\User;

class UsersController extends BaseController
{
    
    public function __construct()
    {
        parent::__construct();
    }
    
    public function index(Request $request)
    {
        if(empty($request->query('q'))){
            $users = User::all()->toArray();
            $users_sort = User::all()->sortByDesc('id')->toArray();
        } else {
            $users = User::where('email', 'like', '%'.$request->query('q').'%')->orWhere('username', 'like', '%'.$request->query('q').'%')->get()->toArray();
            $users_sort = User::where('email', 'like', '%'.$request->query('q').'%')->orWhere('username', 'like', '%'.$request->query('q').'%')->get()->sortByDesc('id')->toArray();
        }
        
        if (count($users) > 0) {
            
            $arr = $users_sort;
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
            'path' => url('control-panel/users')
        ]);
        
        $paginator = $paginator->appends('q', $request->query('q'));
        
        if (count($arr) == 0) {
            
            $arr = array();
            $paginator = array();
        }
        
        return view('admins.users', [
            'userlist' => $paginator
        ]);
    }
    
    public function create()
    {
        //
        return view('admins.dialogs.users_form', [
            'url' => env('APP_URL') . '/control-panel/users/store',
            'username' => '',
            'email' => '', 
            'uid'=>'',
            'type'=>'new'
        ]);
    }
    
    public function store(Request $request)
    {
         
        
        if (User::where(array('email' => $request->get('email')))->count() == 0) {
            
            if (User::where(array('username' =>$request->get('username')))->count() == 0) {
                
                $addMember = new User();
                $addMember->username = $request->get('username');
                $addMember->email = $request->get('email');
                $addMember->password = Hash::make($request->get('password'));
                $addMember->status = 1;
                $addMember->save();
                
                if($request->get('notif') == 1) {
                    Mail::to($request->get('email'))->send(new SendNewPass(['email'=>$request->get('email'), 'new_pass'=>$request->get('password')]));
                }
                  
                return redirect()->intended(route('admin.users'))->withSuccess("User created!");
            } else {
                
                return redirect()->intended(route('admin.users'))->withErrors("Username already registered!");
            }
        } else {
            
            return redirect()->intended(route('admin.users'))->withErrors("Email already registered!");
        }
    }
    
    public function edit(Request $request, $uid)
    {
        //
        return view('admins.dialogs.users_form', [
            'url' => env('APP_URL') . '/control-panel/users/update',
            'username' => User::find($uid)->username,
            'email' => User::find($uid)->email,
            'uid'=>$uid,
            'type'=>'edit'
        ]);
    }
    
    public function update(Request $request)
    { 
      
        User::where(['id' => $request->get('uid')])->update([
            'password' => Hash::make($request->get('password'))
        ]);
        
        if($request->get('notif') == 1) {
            Mail::to(User::find($request->get('uid'))->email)->send(new SendNewPass(['new_pass'=>$request->get('password'), 'email'=>User::find($request->get('uid'))->email]));
        }
        
        session()->flash('success', 'Updated!');
        
        return redirect(route('admin.users'));
    }
    
    public function change_status($user_id, $status_id)
    {   
        if($status_id == 0){
            $new_status = 1;
        } elseif($status_id == 1){
            $new_status = 0;
        }
        
            $update = array(
                'status' => $new_status 
            );
            
            User::where(['id' => $user_id])->update($update);
            
            session()->flash('success', 'Status changed!');
            
            return redirect()->intended(route('admin.users'));
         
    }

    public function destroy($user_id)
    { 
        
        
        User::destroy($user_id);
        GroupsUsers::where(['user_id'=>$user_id])->delete();
        
        session()->flash('success', 'User deleted!');
        
        return redirect()->intended(route('admin.users'));
    }
    
    
    
}