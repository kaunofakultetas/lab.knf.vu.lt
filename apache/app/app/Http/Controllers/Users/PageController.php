<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Users\BaseController;
use App\Http\Controllers\Users\SettingsController; 
use Illuminate\Support\Facades\Validator; 
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\Pages; 

class PageController extends BaseController
{
  
    public function __construct()
    {
        parent::__construct();
    }
    
    public function show($alias)
    {
        
        $validator = Validator::make(['alias'=>$alias], [
            'alias' => ['required', 'string', 'exists:cyber_pages,alias'], 
            ]);
        
        if ($validator->fails()) {
            return redirect()->route('login_users');
        }
        
        return view('users.page', ['data'=>Pages::where(['alias'=>$alias])->get()->first()->toArray()]);
       
       
    } 
    
    
    
    
    
}