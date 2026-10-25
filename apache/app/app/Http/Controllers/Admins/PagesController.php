<?php

// -----------------------------------------------------------
//  [*] PagesController — the lab's content pages
//
//  The main admins' pages list and page form: write, change
//  and delete the content pages Users\PageController shows
//  at /page/{alias}. A new page gets a random UUID as its
//  alias, which the form lets the admin change.
//
//    GET  /control-panel/pages              — the list
//    GET  /control-panel/pages/create       — the empty form
//    POST /control-panel/pages/store        — adds a page
//    GET  /control-panel/pages/edit/{uid}   — the filled form
//    POST /control-panel/pages/update       — changes a page
//    POST /control-panel/pages/delete/{uid} — deletes a page
//
//  Used by:
//    - routes/web.php — the routes above, all main-only
//      (perms)
// -----------------------------------------------------------


namespace App\Http\Controllers\Admins;

use App\Http\Controllers\Admins\BaseController;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\Pages;








// -----------------------------------------------------------
// PagesController
// -----------------------------------------------------------
//
//   __construct — the base's layout data
//   index       — the pages list
//   create      — the empty form
//   store       — adds a page
//   edit        — the filled form
//   update      — changes a page
//   destroy     — deletes a page
//
// Used by:
//   - routes/web.php
// -----------------------------------------------------------

class PagesController extends BaseController
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
    // GET /control-panel/pages
    //
    // The admins.pages view with the pages, 50 to a page of
    // the list — the page query parameter picks one, anything
    // that is not a number meaning the first — or an empty
    // list when there are none.
    //
    // Used by:
    //   - the Pages link of the panel's sidebar, and the
    //     redirects of store and update
    // -----------------------------------------------------------

    public function index(Request $request)
    {
        if (count(Pages::all()->toArray()) > 0) {
            $arr = Pages::all()->toArray();
        } else {
            $arr = array();
        }

        $curr_page = $this->listPage(count($arr));

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






    // -----------------------------------------------------------
    // create
    // -----------------------------------------------------------
    //
    // GET /control-panel/pages/create
    //
    // The admins.pages_form view, empty, posting to store,
    // with a fresh UUID as the alias.
    //
    // Used by:
    //   - the add button of the pages list
    // -----------------------------------------------------------

    public function create()
    {
        return view('admins.pages_form', [
            'url' => env('APP_URL') . '/control-panel/pages/store',
            'name' => '',
            'content' => '',
            'alias' => Str::uuid(),
            'uid'=>''
        ]);
    }






    // -----------------------------------------------------------
    // store
    // -----------------------------------------------------------
    //
    // POST /control-panel/pages/store
    //
    // Adds a page with a name, content and an alias no other
    // page has, then goes to the list with "Created!". The
    // name holds up to 255 characters and the alias up to the
    // 50 of its column, as the database would cut a longer one
    // short, and of letters, digits, dashes and underscores
    // only, as /page/{alias} takes one piece of an address: a
    // slash, ? or # would make the page unreachable. Each
    // field must be text — a list, which only a hand-made
    // request sends, is refused too — and a refused form goes
    // back with the errors and what was typed.
    //
    // Used by:
    //   - the page form, for a new page
    // -----------------------------------------------------------

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'alias' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:cyber_pages']
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        Pages::create([
            'name' => $request->input('name'),
            'content' => $request->input('content'),
            'alias' => $request->input('alias')
        ]);

        session()->flash('success', 'Created!');

        return redirect(route('admin.pages'));
    }






    // -----------------------------------------------------------
    // edit
    // -----------------------------------------------------------
    //
    // GET /control-panel/pages/edit/{uid}
    //
    // The page form filled with the page, posting to update;
    // an unknown page is a 404.
    //
    // Used by:
    //   - the edit links of the pages list
    // -----------------------------------------------------------

    public function edit(Request $request, $uid)
    {
        return view('admins.pages_form', [
            'url' => env('APP_URL') . '/control-panel/pages/update',
            'name' => Pages::findOrFail($uid)->name,
            'content' => Pages::findOrFail($uid)->content,
            'alias' => Pages::findOrFail($uid)->alias,
            'uid' => $uid
        ]);
    }






    // -----------------------------------------------------------
    // update
    // -----------------------------------------------------------
    //
    // POST /control-panel/pages/update
    //
    // Changes the page with the posted uid to the name,
    // content and alias — all three required, text, and held
    // to the lengths of store, the alias free but for the
    // page's own, the uid a whole number, a refused form going
    // back with the errors and what was typed — then goes to
    // the list with "Updated!". The alias is held to store's
    // letters, digits, dashes and underscores only when it
    // changes: a page whose alias came before that rule, with
    // a dot say, keeps its address when it is saved. A page
    // another admin deleted in the meantime goes to the list
    // with "No page!" instead.
    //
    // Used by:
    //   - the page form, for an existing page
    // -----------------------------------------------------------

    public function update(Request $request)
    {
        // The uid is cast, as it goes into the rule's text
        $alias_rules = ['required', 'string', 'max:50', 'unique:cyber_pages,alias,' . (int) $request->input('uid')];

        // An alias kept as it is skips the check of its
        // characters, which an older page's may fail
        if ($request->input('alias') !== Pages::whereKey((int) $request->input('uid'))->value('alias')) {
            $alias_rules[] = 'alpha_dash';
        }

        $validator = Validator::make($request->all(), [
            'uid' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'alias' => $alias_rules
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        if (Pages::whereKey($request->input('uid'))->doesntExist()) {
            return redirect(route('admin.pages'))->withErrors(['No page!']);
        }

        Pages::where(['id' => $request->input('uid')])->update([
            'name' => $request->input('name'),
            'content' => $request->input('content'),
            'alias' => $request->input('alias')
        ]);

        session()->flash('success', 'Updated!');

        return redirect(route('admin.pages'));
    }






    // -----------------------------------------------------------
    // destroy
    // -----------------------------------------------------------
    //
    // POST /control-panel/pages/delete/{uid}
    //
    // Deletes the page and goes back with "Deleted!".
    //
    // Used by:
    //   - Confirm_Dialog() of custom_admin.js — the delete
    //     links of the pages list
    // -----------------------------------------------------------

    public function destroy($uid)
    {
        Pages::destroy($uid);

        session()->flash('success', 'Deleted!');

        return back();
    }
}
