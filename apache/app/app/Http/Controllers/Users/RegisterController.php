<?php

namespace App\Http\Controllers\Users;

use Mail;
use App\Models\User;
use App\Models\Settings;
use App\Mail\UserVerifyMail;
use App\Mail\SendRemindLink;
use App\Mail\SendNewPass;
use App\Http\Controllers\Controller; 
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use ReCaptcha\ReCaptcha;

class RegisterController extends Controller
{
  
    public function create()
    {
         
        return view('users.register', ['reg_status'=>Settings::find(2)->val1]);
    }
    
    public function store(Request $request)
    {
        //$recaptcha = new ReCaptcha(env('NOCAPTCHA_SECRET'));
        //$response = $recaptcha->verify($request->input('g-recaptcha-response'), $request->ip());
        
        // Validate the form data
        $validator = Validator::make($request->all(), [
            'username' => ['required', 'string', 'min:4', 'max:255', 'regex:/^[\pL\pN\.\,\s_-]+$/u', 'unique:cyber_users'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:cyber_users'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],  
            'g-recaptcha-response' => ['required', 'recaptchav3:register,0.6']
            //'g-recaptcha-response' => ['required', function ($attribute, $value, $fail) use ($response) {
            //if (!$response->isSuccess()) {
            //    $fail($response->getErrorCodes()[0]);
           // }
            //}]
        ]);
        
        if ($validator->fails()) {
            return redirect()->route('register_users')->withErrors($validator)->withInput();
        }
        
        $uid_token = Str::random(50);
        
        $user = User::create([
            'user_type' => 1,
            'username' => trim(strip_tags($request->input('username'))),
            'email' => trim(strip_tags($request->input('email'))), 
            'password' => Hash::make($request->input('password')),
            'status'=>0,
            'newsletter' => 0,
            'verify_token'=>$uid_token
        ]);
        
        Mail::to($request->input('email'))->send(new UserVerifyMail(['uid'=>$uid_token]));
        
        session()->flash('success', 'You are registered! Check your email for verification link.');
     
        return redirect()->back();
    }
     
    public function verify(Request $request, $verify_token){
          
        if($verify_token <> ''){
            
            if(User::where(array('verify_token' => $verify_token))->count() == 0) {
                
            } else {
                
                User::where(['verify_token' => $verify_token])->update(['status'=>1,'verify_token'=>'']);
                
                session()->flash('success', 'User verified! Now you can login.');
                
                return redirect()->intended(route('login_users'));
            }
            
        } else {  
            
            return redirect('/'); 
        } 
        
        // session()->flash('success', 'Your account is already verified!');
        
        return redirect('/');
         
    }
     
    public function ForgotPasswordForm(){
        
        return view('users.forgot-password');
        
    }
    
    public function forgotpassword(Request $request){
        
       // $recaptcha = new ReCaptcha(env('NOCAPTCHA_SECRET'));
       // $response = $recaptcha->verify($request->input('g-recaptcha-response'), $request->ip());
        
        $this->validate($request, [
            'email' => ['required', 'email'],
            'g-recaptcha-response' => ['required', 'recaptchav3:forgotpass,0.6']
           // 'g-recaptcha-response' => ['required', function ($attribute, $value, $fail) use ($response) {
          //  if (!$response->isSuccess()) {
          //      $fail($response->getErrorCodes()[0]);
          //  }
          //  }]
        ]);
        
        if (User::where(['email' => $request->input('email')])->where(function ($query) { $query->where('vu_id', '')->orWhereNull('vu_id'); })->count() == 1) {
            
            $uid = Str::random(50);
            
            Mail::to($request->input('email'))->send(new SendRemindLink(['uid'=>$uid]));
            
            User::where(['email'=>$request->input('email')])->update(['remember_token'=>$uid]);
            
        }
        
        session()->flash('success', 'If email exists in our database, you will receive reset link.');
        
        return back();
        
    }
    
    public function reset_pass($reset_token){
 
        $new_pass = Str::random(12);
         
        $user_email = User::select('email')->where('remember_token', '=', $reset_token)->get()->pluck('email')->first();
        
        Mail::to($user_email)->send(new SendNewPass(['email'=>$user_email, 'new_pass'=>$new_pass]));
        
        User::where('remember_token', '=', $reset_token)->update(['password'=>Hash::make($new_pass), 'remember_token'=>'']);
        
        session()->flash('success', 'We just emailed you new password');
        
        return redirect()->intended(route('login_users'));
        
    }
      
    
}
