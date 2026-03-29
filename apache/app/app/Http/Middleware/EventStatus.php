<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\Settings;

class EventStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    { 
        
        
        // if($this->getIp() == '78.63.46.96') {
         //   
             return $next($request);
        // }
        
        // 0 - not started yet
        // 1 - started
        // 2 - finished
        
     //   $dates = explode(" - ", Settings::find(1)->val1);
     //   $event_start = strtotime($dates[0]);
     //   $event_end = strtotime($dates[1]);
     //   $current_date = time();
        
      //  if ($current_date < $event_start) {
            // not started yet
            
      //      session()->flash('info', 'Event not started yet! You will be able to login when the event starts.');
            
      //      return redirect('/');
            
      //  } elseif ($current_date >= $event_start && $current_date < $event_end) {
            // started
              
       //     return $next($request);
            
       // } elseif ($current_date > $event_start && $current_date >= $event_end) {
            // ended
            
       //     Auth::guard('users')->logout();
              
        //    session()->flash('info', 'The event has ended. Thank you for your participation. Winners will be announced on www.cyberthon.lt');
            
        //    return redirect('/');
            
       // } 
    }
    
    
    public static function getIp()
    {
        foreach (array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ) as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip); // just to be safe
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return request()->ip(); // it will return server ip when no client ip found
    }
}