<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Users\SettingsController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UserAuthController extends BaseController
{
    
    protected $redirectTo = '/user/dashboard'; 
    
    public function index()
    {  
        return view('users.login');
    }

    
    public function login(Request $request, Response $response)
    { 
        $validator = Validator::make($request->all(), [ 
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:255']
        ]);
         
        if ($validator->fails()) {
            return redirect()->route('login_users')->withErrors($validator)->withInput();
        }
 
        if(Auth::guard('users')->attempt(['email' => $request->input('email'), 'password' => $request->input('password')])) {
            
            $user = Auth::guard('users')->user();
            
            if ($user->status == 1) {
                
                User::where(['id'=>Auth::guard('users')->id()])->update(['last_login'=>date("Y-m-d H:i:s"),'last_ip'=>SettingsController::getUserIP()]);
                 
                return redirect()->intended('/user/dashboard');
            } else {
                
                Auth::guard('users')->logout();
                
                return redirect()->route('login_users')->withErrors(['Your account is not active']);
            } 
        } else {
            return redirect()->route('login_users')->withErrors(['Wrong login credentials']);
        }
    }
    
    public function logout()
    {
        Auth::guard('users')->logout();
        
        return redirect('/');
    }
    
    public function dashboard(Request $request)
    { 
        return view('users.dashboard');
    }
    
    public function cp(Request $request){
        
        return redirect('/user/dashboard');
    }
    
    public static function check_member($memberarr){ 
        
       
        $exists = User::where(['vu_id'=>$memberarr['urn:oid:0.9.2342.19200300.100.1.1'][0]])->where('vu_id','!=', NULL)->count();
        
        if($exists == 0){
            // register new
            
            $name = $memberarr['urn:oid:2.5.4.42'][0].' '.$memberarr['urn:oid:2.5.4.4'][0];
            
            User::create([
                'username'=>$memberarr['urn:oid:0.9.2342.19200300.100.1.1'][0],
                'vu_id'=>$memberarr['urn:oid:0.9.2342.19200300.100.1.1'][0],
                'email'=>$memberarr['urn:oid:0.9.2342.19200300.100.1.3'][0],
                'display_name'=>$name,
                //'affiliation'=>serialize($memberarr['eduPersonAffiliation']),
                //'member_of'=>serialize($memberarr['memberOf']),
                'status'=>1,
            ]);
            
            
        }
        
        $id = User::where(['vu_id'=>$memberarr['urn:oid:0.9.2342.19200300.100.1.1'][0]])->get()->pluck('id')->first();
     
        User::where(['vu_id'=>$memberarr['urn:oid:0.9.2342.19200300.100.1.1'][0]])->where('vu_id','!=', NULL)->update(['last_login'=>date("Y-m-d H:i:s"), 'last_ip'=>SettingsController::getUserIP()]);
 
        return $id;
        
    }
    
    public function logout_saml()
    {
        
        Auth::guard('users')->logout();
        $as = new \SimpleSAML\Auth\Simple('default-sp');
        
        
        return redirect($as->getLogoutURL(env('APP_URL').'/'));
    }
}
