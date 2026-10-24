<?php

// -----------------------------------------------------------
//  [*] PartnersController — the partners and their logos
//
//  The main admins' partners page and its dialog: list,
//  add, change and delete the partners whose logos the
//  challenge pages show. A logo is checked to be an image
//  of 2 MB at most and stored in storage/app/public — the
//  folder the site serves under /upload — under a random
//  32-character name with the extension its content shows,
//  never the one it was sent with. In production that
//  folder is _DATA/apache/public, mounted read-only
//  (docker-compose.yml), so storing a logo there fails with
//  a server error. No link of the panel leads to the page;
//  it is reached by its address.
//
//    GET  /control-panel/partners                   — the page
//    GET  /control-panel/dialog/partners/new        — add dialog
//    POST /control-panel/dialog/partners/store      — adds
//    GET  /control-panel/dialog/partners/edit/{uid} — edit dialog
//    POST /control-panel/dialog/partners/update     — changes
//    POST /control-panel/partners/delete/{uid}      — deletes
//
//  Used by:
//    - routes/web.php — the routes above, all main-only
//      (perms)
// -----------------------------------------------------------


namespace App\Http\Controllers\Admins;

use App\Http\Controllers\Admins\BaseController;
use Illuminate\Http\Request;
use App\Models\Partners;








// -----------------------------------------------------------
// PartnersController
// -----------------------------------------------------------
//
// A replaced or deleted partner's logo file stays in the
// folder; nothing deletes it.
//
//   __construct — the base's layout data
//   index       — the partners page
//   create      — the add dialog
//   store       — adds a partner
//   edit        — the edit dialog
//   update      — changes a partner
//   destroy     — deletes a partner
//
// Used by:
//   - routes/web.php
// -----------------------------------------------------------

class PartnersController extends BaseController
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
    // GET /control-panel/partners
    //
    // The admins.partners view with every partner.
    //
    // Used by:
    //   - whoever has the address — no link of the panel leads
    //     here
    // -----------------------------------------------------------

    public function index()
    {
        return view('admins.partners', [
            'recordlist' => Partners::all()->toArray()
        ]);
    }






    // -----------------------------------------------------------
    // create
    // -----------------------------------------------------------
    //
    // GET /control-panel/dialog/partners/new
    //
    // The admins.dialogs.partners_form view, empty, for a new
    // partner.
    //
    // Used by:
    //   - partners_form() of dialogs.js — the add button of the
    //     partners page
    // -----------------------------------------------------------

    public function create()
    {
        return view('admins.dialogs.partners_form', [
            'type'=>'new'
        ]);
    }






    // -----------------------------------------------------------
    // store
    // -----------------------------------------------------------
    //
    // POST /control-panel/dialog/partners/store
    //
    // Adds a partner: a name of up to 255 characters and a
    // logo — jpg, png, gif or webp — stored as described
    // above; then back with "Created!". A refused form goes
    // back with Laravel's errors.
    //
    // Used by:
    //   - the partner dialog, for a new partner
    // -----------------------------------------------------------

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'picture' => 'required|file|image|mimes:jpg,jpeg,png,gif,webp|max:2048',
        ]);

        $file = $request->file('picture');
        $ext = $file->guessExtension() ?: 'png';
        if ($ext === 'jpeg') $ext = 'jpg';
        $file_name = bin2hex(random_bytes(16)) . '.' . $ext;

        $file->move(storage_path('app/public'), $file_name);

        $addPartner = new Partners();
        $addPartner->name = $request->input('name');
        $addPartner->picture = $file_name;
        $addPartner->save();

        session()->flash('success', 'Created!');

        return back();
    }






    // -----------------------------------------------------------
    // edit
    // -----------------------------------------------------------
    //
    // GET /control-panel/dialog/partners/edit/{uid}
    //
    // The partner dialog filled with the partner's name; an
    // unknown partner is a 404.
    //
    // Used by:
    //   - partners_form() of dialogs.js — the edit buttons of
    //     the partners page
    // -----------------------------------------------------------

    public function edit(Request $request, $uid)
    {
        return view('admins.dialogs.partners_form', [
            'name' => Partners::findOrFail($uid)->name,
            'type'=>'edit',
            'uid'=>$uid
        ]);
    }






    // -----------------------------------------------------------
    // update
    // -----------------------------------------------------------
    //
    // POST /control-panel/dialog/partners/update
    //
    // Changes the partner with the posted uid: its name and a
    // new logo, which the form requires every time — a rename
    // alone is refused. Then back with "Updated!".
    //
    // Used by:
    //   - the partner dialog, for an existing partner
    // -----------------------------------------------------------

    public function update(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'picture' => 'required|file|image|mimes:jpg,jpeg,png,gif,webp|max:2048',
            'uid' => 'required|integer',
        ]);

        $file = $request->file('picture');
        $ext = $file->guessExtension() ?: 'png';
        if ($ext === 'jpeg') $ext = 'jpg';
        $file_name = bin2hex(random_bytes(16)) . '.' . $ext;

        $file->move(storage_path('app/public'), $file_name);

        $update = array(
            'name' => $request->input('name'),
            'picture' => $file_name
        );

        Partners::where([
            'id' => $request->input('uid')
        ])->update($update);

        session()->flash('success', 'Updated!');

        return back();
    }






    // -----------------------------------------------------------
    // destroy
    // -----------------------------------------------------------
    //
    // POST /control-panel/partners/delete/{uid}
    //
    // Deletes the partner and goes back with "Deleted!". Its
    // challenges keep pointing at it, and their page then
    // leaves the partner card out.
    //
    // Used by:
    //   - Confirm_Dialog() of custom_admin.js — the delete
    //     buttons of the partners page
    // -----------------------------------------------------------

    public function destroy($uid)
    {
        session()->flash('success', 'Deleted!');

        Partners::destroy($uid);

        return back();
    }
}
