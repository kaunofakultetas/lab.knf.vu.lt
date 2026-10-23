<?php

// -----------------------------------------------------------
//  [*] CategoriesController — the challenge categories
//
//  The categories page of the control panel and its
//  dialog: list, add, rename and delete the categories the
//  challenges are filed under. Every route of it is
//  main-only (perms).
//
//    GET  /control-panel/categories                 — the page
//    GET  /control-panel/dialog/category/new        — add dialog
//    POST /control-panel/dialog/category/store      — adds
//    GET  /control-panel/dialog/category/edit/{uid} — rename dialog
//    POST /control-panel/dialog/category/update     — renames
//    POST /control-panel/category/delete/{uid}      — deletes
//
//  Used by:
//    - routes/web.php — the routes above
// -----------------------------------------------------------


namespace App\Http\Controllers\Admins;

use App\Http\Controllers\Admins\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\ChallengesCategories;
use App\Models\Categories;








// -----------------------------------------------------------
// CategoriesController
// -----------------------------------------------------------
//
// A category needs a name, when it is added and when it
// is renamed.
//
//   __construct — the base's layout data
//   index       — the categories page
//   create      — the add dialog
//   store       — adds a category
//   edit        — the rename dialog
//   update      — renames a category
//   destroy     — deletes a category
//
// Used by:
//   - routes/web.php
// -----------------------------------------------------------

class CategoriesController extends BaseController
{






    // -----------------------------------------------------------
    // __construct
    // -----------------------------------------------------------
    //
    // Only runs the base constructor, which shares the
    // layout's data.
    //
    // Used by:
    //   - Laravel, when it builds the controller for a request
    // -----------------------------------------------------------

    public function __construct()
    {
        parent::__construct();
    }






    // -----------------------------------------------------------
    // index
    // -----------------------------------------------------------
    //
    // GET /control-panel/categories
    //
    // The admins.categories view with every category.
    //
    // Used by:
    //   - the Categories link of the panel's sidebar
    // -----------------------------------------------------------

    public function index()
    {
        return view('admins.categories', [
            'recordlist' => Categories::all()->toArray()
        ]);
    }






    // -----------------------------------------------------------
    // create
    // -----------------------------------------------------------
    //
    // GET /control-panel/dialog/category/new
    //
    // The admins.dialogs.category_form view, empty, for a new
    // category.
    //
    // Used by:
    //   - category_form() of dialogs.js — the add button of
    //     the categories page
    // -----------------------------------------------------------

    public function create()
    {
        return view('admins.dialogs.category_form', [
            'type'=>'new'
        ]);
    }






    // -----------------------------------------------------------
    // store
    // -----------------------------------------------------------
    //
    // POST /control-panel/dialog/category/store
    //
    // Adds a category with the name as sent, then goes back
    // with "Created!"; a form without a name, or with one
    // longer than the 255 characters its column holds, goes
    // back with the error.
    //
    // Used by:
    //   - the category dialog, for a new category
    // -----------------------------------------------------------

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        Categories::create(['name' => $request->get('name')]);

        session()->flash('success', 'Created!');

        return back();
    }






    // -----------------------------------------------------------
    // edit
    // -----------------------------------------------------------
    //
    // GET /control-panel/dialog/category/edit/{uid}
    //
    // The category dialog filled with the category's name; an
    // unknown category is a 404.
    //
    // Used by:
    //   - category_form() of dialogs.js — the rename buttons
    //     of the categories page
    // -----------------------------------------------------------

    public function edit(Request $request, $uid)
    {
        return view('admins.dialogs.category_form', [
            'name' => Categories::findOrFail($uid)->name,
            'type'=>'edit',
            'uid'=>$uid
        ]);
    }






    // -----------------------------------------------------------
    // update
    // -----------------------------------------------------------
    //
    // POST /control-panel/dialog/category/update
    //
    // Renames the category with the posted uid, then goes back
    // with "Updated!"; an empty name or one longer than 255
    // characters goes back with the error, and a category
    // another admin deleted in the meantime with "No
    // category!".
    //
    // Used by:
    //   - the category dialog, for an existing category
    // -----------------------------------------------------------

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        if (Categories::whereKey($request->get('uid'))->doesntExist()) {
            return back()->withErrors(['No category!']);
        }

        Categories::where(['id' => $request->get('uid')])->update(['name' => $request->get('name')]);

        session()->flash('success', 'Updated!');

        return back();
    }






    // -----------------------------------------------------------
    // destroy
    // -----------------------------------------------------------
    //
    // POST /control-panel/category/delete/{uid}
    //
    // Takes the category off every challenge, deletes it, and
    // goes back with "Deleted!".
    //
    // Used by:
    //   - Confirm_Dialog() of custom_admin.js — the delete
    //     buttons of the categories page
    // -----------------------------------------------------------

    public function destroy($uid)
    {
        session()->flash('success', 'Deleted!');

        ChallengesCategories::where(['cat_id'=>$uid])->delete();
        Categories::destroy($uid);

        return back();
    }
}
