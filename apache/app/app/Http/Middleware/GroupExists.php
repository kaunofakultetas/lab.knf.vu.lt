<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Groups;
use App\Models\GroupsUsers;

class GroupExists
{
  
    public function handle($request, Closure $next)
    {  
              
        $public_id =  $request->route('public_id');
        $group_id = Groups::where(['public_id'=>$public_id])->get()->pluck('id')->first();
        $user_id = Auth::guard('users')->id();
        $vu_id = User::where(['id'=>$user_id])->get()->pluck('vu_id')->first();
        
        if(GroupsUsers::where(['group_id' => $group_id, 'vu_id' => $vu_id])->count() > 0) {
                
                return $next($request);
        } else {
                
                abort(404);
        }
        
       
    }
    
    
}