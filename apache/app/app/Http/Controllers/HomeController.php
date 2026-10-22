<?php

// -----------------------------------------------------------
//  [*] HomeController — an unused address-to-country report
//
//  One action that no route reaches: a report that looked
//  up the country of every IPv6 address players solved
//  from. Dead code, kept as found — it could not run here
//  anyway (see ip).
//
//  Used by:
//    - nothing at the moment
// -----------------------------------------------------------


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ChallengesSubmitted;








// -----------------------------------------------------------
// HomeController
// -----------------------------------------------------------
//
// One action, ip.
//
// Used by:
//   - nothing at the moment
// -----------------------------------------------------------

class HomeController extends Controller
{






    // -----------------------------------------------------------
    // ip
    // -----------------------------------------------------------
    //
    // Would print every distinct IPv6 address of the solves
    // with its country from the ipgeolocation.io API
    // (IPGEO_API_KEY), and store the country on the solves.
    // It cannot work here: the stack's egress allows no
    // ipgeolocation.io, and cyber_challenges_submitted has no
    // country column. IPv4 addresses are skipped.
    //
    // Used by:
    //   - nothing at the moment
    // -----------------------------------------------------------

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
