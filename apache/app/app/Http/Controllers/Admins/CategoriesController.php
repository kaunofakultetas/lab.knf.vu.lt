<?php

namespace App\Http\Controllers\Admins;

use App\Http\Controllers\Admins\BaseController;
use Illuminate\Http\Request;
use App\Models\ChallengesCategories;
use App\Models\Categories;

class CategoriesController extends BaseController
{
   
    public function __construct()
    {
        parent::__construct();
    }
    
    public function index()
    {
        //
        return view('admins.categories', [
            'recordlist' => Categories::all()->toArray()
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    { 
        
        return view('admins.dialogs.category_form', [
            'type'=>'new'
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
        Categories::create(['name' => $request->get('name')]);
        
        session()->flash('success', 'Created!');
        
        return back();
    }
 

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $uid)
    {
        return view('admins.dialogs.category_form', [
            'name' => Categories::find($uid)->name,
            'type'=>'edit',
            'uid'=>$uid
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
        Categories::where(['id' => $request->get('uid')])->update(['name' => $request->get('name')]);
        
        session()->flash('success', 'Updated!');
        
        return back();
        
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($uid)
    {
        session()->flash('success', 'Deleted!');
        
        ChallengesCategories::where(['cat_id'=>$uid])->delete();
        Categories::destroy($uid);
        
        return back();
        
    }
}
