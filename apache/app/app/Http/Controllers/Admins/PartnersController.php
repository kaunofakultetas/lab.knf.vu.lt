<?php

namespace App\Http\Controllers\Admins;

use App\Http\Controllers\Admins\BaseController;
use Illuminate\Http\Request;
use App\Models\Partners;

class PartnersController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        return view('admins.partners', [
            'recordlist' => Partners::all()->toArray()
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    { 
        
        return view('admins.dialogs.partners_form', [
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
        $file = $request->file('picture');
        $file_name = md5(sha1(md5(time()))) . '.' . $file->getClientOriginalExtension();
        
        $file->move(storage_path('app/public'), $file_name);
        
        $addPartner = new Partners();
        $addPartner->name = $request->get('name');
        $addPartner->picture = $file_name;
        $addPartner->save();
        
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
        return view('admins.dialogs.partners_form', [
            'name' => Partners::find($uid)->name,
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
        $file = $request->file('picture');
        $file_name = md5(sha1(md5(time()))) . '.' . $file->getClientOriginalExtension();
        
        $file->move(storage_path('app/public'), $file_name);
        
        $update = array(
            'name' => $request->get('name'),
            'picture' => $file_name
        );
        
        Partners::where([
            'id' => $request->get('uid')
        ])->update($update);
        
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
        
        Partners::destroy($uid);
        
        return back();
        
    }
}