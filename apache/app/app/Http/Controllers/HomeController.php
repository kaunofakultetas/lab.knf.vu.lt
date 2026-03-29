<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ChallengesSubmitted;

class HomeController extends Controller
{
    
    
    public function ip()
    {
        
        $ips = ChallengesSubmitted::select('ip')->distinct()->get()->pluck('ip')->toArray();
        echo '<pre>';
          
         
        foreach($ips as $k => $v){
            
            if (filter_var($v, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                 
            echo $v;
            
            $d = json_decode(file_get_contents("https://api.ipgeolocation.io/ipgeo?apiKey=" . env('IPGEO_API_KEY') . "&ip=$v"), 1);
            
            if(array_key_exists("country_name",$d) && is_array($d) && isset($d)){
                echo " - ".$d['country_name'];
                ChallengesSubmitted::where(["ip"=>$v])->update(["country"=>$d['country_name']]);
            }
            
            
            echo '<br>';
            }
            
        }
        
    }
}
