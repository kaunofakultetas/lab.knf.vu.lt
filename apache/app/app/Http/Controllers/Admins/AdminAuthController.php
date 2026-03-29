<?php

namespace App\Http\Controllers\Admins;

use Session;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Admin;
use App\Models\ChallengesSubmitted;
use Illuminate\Http\Request;  

class AdminAuthController extends Controller
{
    protected $redirectTo = '/control-panel/dashboard';
      
    public function index()
    {
        return view('admins.login');
    }
     
    public function login(Request $request)
    {
        
         
        $this->validate($request, [
            'email'   => 'required|email',
            'password' => 'required|min:6'
        ]);
        
         
        
        $credentials = $request->only('email', 'password');
          
        if (Auth::guard('admins')->attempt($credentials)) {
            // Authentication passed...
            return redirect()->intended('/control-panel/dashboard');
        } else {
            return back();
        }
    }
     
    public function logout()
    {
     //   Auth::guard('admins')->logout(); 
       // return redirect('/control-panel'); 
       
     Auth::guard('admins')->logout(); 
     $as = new \SimpleSAML\Auth\Simple('default-sp');
            
     return redirect($as->getLogoutURL(env('APP_URL').'/control-panel'));
       
    } 
    
    public function cp_login(Request $request){
        
        return redirect('/control-panel/dashboard');
    }
    
    public static function check_admin($memberarr){
         
        $id = Admin::where(['vu_id'=>$memberarr['urn:oid:0.9.2342.19200300.100.1.1'][0]])->get()->pluck('id')->first();
        
        if($id>0){
            Session::put('main', Admin::select('main')->where(['id' => $id])->get()->pluck('main')->first());
        }
         
        return $id;
        
    }
    
    
}
