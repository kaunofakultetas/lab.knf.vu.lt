<?php
namespace App\Http\Controllers\Admins;

use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request; 
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth; 
use App\Models\Settings;
use App\Models\ChallengesHistory;
use App\Models\Admin;   
use View; 

class BaseController extends Controller
{

    public function __construct()
    {
       // $this->middleware('auth:users');

        $this->middleware(function ($request, $next) {
       
             
            
            $vu_id = Admin::where(['id'=>Auth::guard('admins')->id()])->get()->pluck('vu_id')->first();
            $main = Admin::where(['id'=>Auth::guard('admins')->id()])->get()->pluck('main')->first();
             
            //View::share('username', $username);
            View::share('vu_id', $vu_id); 
            View::share('main', $main); 
             
            
            return $next($request);
            
            
            
            
        });
    }
}