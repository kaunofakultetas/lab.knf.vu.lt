<?php

namespace App\Http\Controllers\Admins;

use App\Http\Controllers\Admins\BaseController;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\Pages;

class PagesController extends BaseController
{ 
    public function __construct()
    {
        parent::__construct();
    }
    
    public function index(Request $request)
    {
        //
        if (count(Pages::all()->toArray()) > 0) {
            
            $arr = Pages::all()->toArray();
        } else {
            
            $arr = array();
        }
        
        if (empty($request->get('page')) || ! is_numeric($request->get('page'))) {
            
            $curr_page = 1;
        } else {
            
            $curr_page = $request->get('page');
        }
        
        $perPage = 50;
        
        $currentItems = array_slice($arr, $perPage * ($curr_page - 1), $perPage);
        
        $paginator = new LengthAwarePaginator($currentItems, count($arr), $perPage, $curr_page, [
            'path' => url('control-panel/pages')
        ]);
        
        if (count($arr) == 0) { 
            
            $arr = array();
            $paginator = array();
        }
        return view('admins.pages', [
            'recordlist' => $paginator
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
        return view('admins.pages_form', [
            'url' => env('APP_URL') . '/control-panel/pages/store',
            'name' => '',
            'content' => '',
            'alias' => Str::uuid(),
            'uid'=>''
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
       
        $validator = Validator::make($request->all(), [
            'name' => ['required'],
            'content' => ['required'],
            'alias' => ['required', 'unique:cyber_pages']
        ]);
        
        if ($validator->fails()) {
            return back();
        }
        
        Pages::create([
            'name' => $request->get('name'),
            'content' => $request->get('content'),
            'alias' => $request->get('alias')
        ]);
        
        session()->flash('success', 'Created!');
        
        return redirect(route('admin.pages'));
        
    } 

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $uid)
    {
        //
        
        return view('admins.pages_form', [
            'url' => env('APP_URL') . '/control-panel/pages/update',
            'name' => Pages::find($uid)->name,
            'content' => Pages::find($uid)->content,
            'alias' => Pages::find($uid)->alias,
            'uid' => $uid
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        //
        Pages::where(['id' => $request->get('uid')])->update([
            'name' => $request->get('name'),
            'content' => $request->get('content'),
            'alias' => $request->get('alias')
        ]);
        
        session()->flash('success', 'Updated!');
        
        return redirect(route('admin.pages'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($uid)
    {
        
        Pages::destroy($uid);
        
        session()->flash('success', 'Deleted!');
        
        return back();
    }
}
