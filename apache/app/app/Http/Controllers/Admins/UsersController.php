<?php

// -----------------------------------------------------------
//  [*] UsersController — the player accounts
//
//  The control panel's accounts page and its dialog: search
//  the players, create an account, give one a new password,
//  switch one active or inactive, delete one. The page and
//  the create dialog are open to every admin; a new
//  password, the switch and deletion are main-only. Creating
//  and changing an account can mail the password to its
//  owner.
//
//    GET  /control-panel/users                              — the page
//    GET  /control-panel/dialog/users/new                   — create dialog
//    POST /control-panel/dialog/users/store                 — creates
//    GET  /control-panel/dialog/users/edit/{uid}            — password dialog
//    POST /control-panel/dialog/users/update                — new password
//    POST /control-panel/users/status/{user_id}/{status_id} — switches
//    POST /control-panel/users/delete/{uid}                 — deletes
//    GET  /control-panel/users/create                       — create dialog
//    POST /control-panel/users/store                        — creates
//
//  The last two duplicate the dialog routes; no view uses
//  them.
//
//  Used by:
//    - routes/web.php — the routes above
// -----------------------------------------------------------


namespace App\Http\Controllers\Admins;

use Mail;
use App\Mail\SendNewPass;
use App\Http\Controllers\Admins\SettingsController;
use App\Http\Controllers\Admins\BaseController;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use App\Models\GroupsUsers;
use App\Models\User;








// -----------------------------------------------------------
// UsersController
// -----------------------------------------------------------
//
// An empty create form goes back with the errors; the
// password form is not validated.
//
//   __construct   — the base's layout data
//   index         — the accounts page
//   create        — the create dialog
//   store         — creates an account
//   edit          — the password dialog
//   update        — sets a new password
//   change_status — switches an account
//   destroy       — deletes an account
//
// Used by:
//   - routes/web.php
// -----------------------------------------------------------

class UsersController extends BaseController
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
    // GET /control-panel/users
    //
    // The admins.users view: the accounts, newest first, 50 to
    // a page of the list, narrowed to those whose e-mail or
    // username contains the q query parameter when one is
    // given; the page links keep q.
    //
    // Used by:
    //   - the Users link of the panel's sidebar, and the
    //     redirects of the account actions
    // -----------------------------------------------------------

    public function index(Request $request)
    {
        if(empty($request->query('q'))){
            $users = User::all()->toArray();
            $users_sort = User::all()->sortByDesc('id')->toArray();
        } else {
            $users = User::where('email', 'like', '%'.$request->query('q').'%')->orWhere('username', 'like', '%'.$request->query('q').'%')->get()->toArray();
            $users_sort = User::where('email', 'like', '%'.$request->query('q').'%')->orWhere('username', 'like', '%'.$request->query('q').'%')->get()->sortByDesc('id')->toArray();
        }

        if (count($users) > 0) {
            $arr = $users_sort;
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
            'path' => url('control-panel/users')
        ]);

        $paginator = $paginator->appends('q', $request->query('q'));

        if (count($arr) == 0) {
            $arr = array();
            $paginator = array();
        }

        return view('admins.users', [
            'userlist' => $paginator
        ]);
    }






    // -----------------------------------------------------------
    // create
    // -----------------------------------------------------------
    //
    // GET /control-panel/dialog/users/new
    // GET /control-panel/users/create
    //
    // The admins.dialogs.users_form view, empty. The url it is
    // handed goes unused: the dialog posts to the dialog
    // store route itself.
    //
    // Used by:
    //   - users_form() of dialogs.js — the add button of the
    //     accounts page
    // -----------------------------------------------------------

    public function create()
    {
        return view('admins.dialogs.users_form', [
            'url' => env('APP_URL') . '/control-panel/users/store',
            'username' => '',
            'email' => '',
            'uid'=>'',
            'type'=>'new'
        ]);
    }






    // -----------------------------------------------------------
    // store
    // -----------------------------------------------------------
    //
    // POST /control-panel/dialog/users/store
    // POST /control-panel/users/store
    //
    // Creates an active local account with the username,
    // e-mail and password as sent, unless the e-mail or the
    // username is taken; with notif set to 1, mails the
    // password to the new account (SendNewPass). Then to the
    // accounts page with "User created!", or with the error;
    // a form missing the username, e-mail or password goes
    // back with the errors.
    //
    // Used by:
    //   - the account dialog, for a new account
    // -----------------------------------------------------------

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required',
            'email' => 'required',
            'password' => 'required'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        if (User::where(array('email' => $request->get('email')))->count() == 0) {
            if (User::where(array('username' =>$request->get('username')))->count() == 0) {
                $addMember = new User();
                $addMember->username = $request->get('username');
                $addMember->email = $request->get('email');
                $addMember->password = Hash::make($request->get('password'));
                $addMember->status = 1;
                $addMember->save();

                if($request->get('notif') == 1) {
                    Mail::to($request->get('email'))->send(new SendNewPass(['email'=>$request->get('email'), 'new_pass'=>$request->get('password')]));
                }

                return redirect()->intended(route('admin.users'))->withSuccess("User created!");
            } else {
                return redirect()->intended(route('admin.users'))->withErrors("Username already registered!");
            }
        } else {
            return redirect()->intended(route('admin.users'))->withErrors("Email already registered!");
        }
    }






    // -----------------------------------------------------------
    // edit
    // -----------------------------------------------------------
    //
    // GET /control-panel/dialog/users/edit/{uid}
    //
    // The account dialog showing the account's username and
    // e-mail, for a new password. The url it is handed goes
    // unused, as in create.
    //
    // Used by:
    //   - users_form() of dialogs.js — the edit buttons of the
    //     accounts page
    // -----------------------------------------------------------

    public function edit(Request $request, $uid)
    {
        return view('admins.dialogs.users_form', [
            'url' => env('APP_URL') . '/control-panel/users/update',
            'username' => User::find($uid)->username,
            'email' => User::find($uid)->email,
            'uid'=>$uid,
            'type'=>'edit'
        ]);
    }






    // -----------------------------------------------------------
    // update
    // -----------------------------------------------------------
    //
    // POST /control-panel/dialog/users/update
    //
    // Gives the account with the posted uid the password as
    // sent — the only thing the dialog changes — and, with
    // notif set to 1, mails it to the account (SendNewPass).
    // Then to the accounts page with "Updated!".
    //
    // Used by:
    //   - the account dialog, for an existing account
    // -----------------------------------------------------------

    public function update(Request $request)
    {
        User::where(['id' => $request->get('uid')])->update([
            'password' => Hash::make($request->get('password'))
        ]);

        if($request->get('notif') == 1) {
            Mail::to(User::find($request->get('uid'))->email)->send(new SendNewPass(['new_pass'=>$request->get('password'), 'email'=>User::find($request->get('uid'))->email]));
        }

        session()->flash('success', 'Updated!');

        return redirect(route('admin.users'));
    }






    // -----------------------------------------------------------
    // change_status
    // -----------------------------------------------------------
    //
    // POST /control-panel/users/status/{user_id}/{status_id}
    //
    // Switches the account to the other status — the address
    // carries the current one, 0 or 1 — and goes to the
    // accounts page with "Status changed!"; any other status
    // in the address leaves the new one undefined and ends in
    // a server error. An inactive account can no longer sign
    // in, through the login form or the VU SSO alike.
    //
    // Used by:
    //   - Post_Link() of custom_admin.js — the status switches
    //     of the accounts page
    // -----------------------------------------------------------

    public function change_status($user_id, $status_id)
    {
        if($status_id == 0){
            $new_status = 1;
        } elseif($status_id == 1){
            $new_status = 0;
        }

        $update = array(
            'status' => $new_status
        );

        User::where(['id' => $user_id])->update($update);

        session()->flash('success', 'Status changed!');

        return redirect()->intended(route('admin.users'));
    }






    // -----------------------------------------------------------
    // destroy
    // -----------------------------------------------------------
    //
    // POST /control-panel/users/delete/{uid}
    //
    // Deletes the account and, for a VU account, its group
    // memberships, which are kept by VU id. The solves and
    // feedback of the account stay.
    //
    // Used by:
    //   - Confirm_Dialog() of custom_admin.js — the delete
    //     buttons of the accounts page
    // -----------------------------------------------------------

    public function destroy($user_id)
    {
        $vu_id = User::where(['id' => $user_id])->value('vu_id');

        User::destroy($user_id);

        if ($vu_id) {
            GroupsUsers::where(['vu_id' => $vu_id])->delete();
        }

        session()->flash('success', 'User deleted!');

        return redirect()->intended(route('admin.users'));
    }
}
