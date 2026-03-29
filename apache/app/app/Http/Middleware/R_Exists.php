<?php

namespace App\Http\Middleware;

use Closure;
use Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\Challenges;
use App\Models\Groups;
use App\Models\GroupsChallenges;
use App\Models\GroupsUsers;

class R_Exists
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, $type)
    {


        if($type =='group_owned'){

        $uid = $request->route('uid');

            if(Groups::where(['id'=>$uid])->count() == 1) {
                
                if(Session::get('main') == 1 || Groups::where(['id'=>$uid, 'created_by'=>Auth::guard('admins')->id()])->count() == 1){

                    return $next($request);
                 
                } else {
                    
                    return back()->withErrors(['You don\'t have permissions to do this!']);
                    
                }

            } else {
                
                return back()->withErrors(['No group!']);
                
            }

       }
       
       if($type =='group_owned_uid'){
           
           $uid = $request->get('uid');
           
           if(Groups::where(['id'=>$uid])->count() == 1) {
               
               if(Session::get('main') == 1 || Groups::where(['id'=>$uid, 'created_by'=>Auth::guard('admins')->id()])->count() == 1){
                   
                   return $next($request);
                   
               } else {
                   
                   return back()->withErrors(['You don\'t have permissions to do this!']);
                   
               }
               
           } else {
               
               return back()->withErrors(['No group!']);
               
           }
           
           
       }
       
       
       if($type =='challenge_owned'){
           
           $uid = $request->route('uid');
           
           if(Challenges::where(['id'=>$uid])->count() == 1) {
               
               if(Session::get('main') == 1 || Challenges::where(['id'=>$uid, 'created_by'=>Auth::guard('admins')->id()])->count() == 1){
                   
                   return $next($request);
                   
               } else {
                   
                   return back()->withErrors(['You don\'t have permissions to do this!']);
                   
               }
               
           } else {
               
               return back()->withErrors(['No challenge!']);
               
           }
           
       }
       
       if($type =='challenge_owned_uid'){
           
           $uid = $request->get('uid');
           
           if(Challenges::where(['id'=>$uid])->count() == 1) {
               
               if(Session::get('main') == 1 || Challenges::where(['id'=>$uid, 'created_by'=>Auth::guard('admins')->id()])->count() == 1){
                   
                   return $next($request);
                   
               } else {
                   
                   return back()->withErrors(['You don\'t have permissions to do this!']);
                   
               }
               
           } else {
               
               return back()->withErrors(['No challenge!']);
               
           }
           
       }

 

    }
}