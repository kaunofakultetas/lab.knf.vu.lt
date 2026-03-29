<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth; 

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


      //  if($type =='lm_cat'){

        //    $uid = $request->route('cat_id');

       //     if(LMCategory::where(['id'=>$uid])->count() == 1) {

       //         return $next($request);

//} else {
      //          abort(404);
       //     }

      //  } 

 

    }
}