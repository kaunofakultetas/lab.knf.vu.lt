<?php

namespace App\Http\Controllers\Admins;

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Admins\BaseController;
use Illuminate\Support\Facades\Hash;
use App\Models\Settings;
use App\Models\Admin;
use Illuminate\Http\Request;

class SettingsController extends BaseController
{
    
    public function __construct()
    {
        parent::__construct();
    }
    
    public function index()
    {
        return view('admins.settings', [
            'event_timerange' => Settings::find(1)->val1,
            'reg_open' => Settings::find(2)->val1
            
        ]);
    }
    
    public function event_data(Request $req){
        
        Settings::where(['id' => 1])->update(['val1'=>$req->get('event_timerange')]);
        
        session()->flash('success', 'Updated!');
        
        return back();
        
    }
    
    public function reg_open(Request $req){
        
        Settings::where(['id' => 2])->update(['val1'=>$req->get('reg_open')]);
        
        session()->flash('success', 'Updated!');
        
        return back();
        
    }
    
    public static function ordinal($number) {
        $ends = array('th','st','nd','rd','th','th','th','th','th','th');
        if ((($number % 100) >= 11) && (($number%100) <= 13))
            return $number. 'th';
            else
                return $number. $ends[$number % 10];
    }
    
    public static function time_elapsed_string($datetime, $full = false)
    {
        $now = new \DateTime();
        $ago = new \DateTime($datetime);
        $diff = $now->diff($ago);
        
        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;
        
        $string = array(
            'y' => 'year',
            'm' => 'month',
            'w' => 'week',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second'
        );
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }
        
        if (! $full)
            $string = array_slice($string, 0, 1);
            return $string ? implode(', ', $string) . ' ago' : 'just now';
    } 
     
}