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
use App\Models\ChallengesSubmitted;
use App\Models\ChallengesHistory;








// -----------------------------------------------------------
// UsersController
// -----------------------------------------------------------
//
// The create form needs a username, an e-mail address and
// a password of at least 8 characters, the password form
// such a password; a form without them goes back with the
// errors.
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
    // given — 0 included, and nothing that is not text — and
    // the page links keep q.
    //
    // Used by:
    //   - the All users link of the panel's sidebar, and the
    //     redirects of the account actions
    // -----------------------------------------------------------

    public function index(Request $request)
    {
        $q = $request->query('q');
        $q = is_string($q) ? $q : '';

        if($q === ''){
            $users = User::all()->toArray();
            $users_sort = User::all()->sortByDesc('id')->toArray();
        } else {
            $users = User::where('email', 'like', '%'.$q.'%')->orWhere('username', 'like', '%'.$q.'%')->get()->toArray();
            $users_sort = User::where('email', 'like', '%'.$q.'%')->orWhere('username', 'like', '%'.$q.'%')->get()->sortByDesc('id')->toArray();
        }

        if (count($users) > 0) {
            $arr = $users_sort;
        } else {
            $arr = array();
        }

        $curr_page = $this->listPage();

        $perPage = 50;

        $currentItems = array_slice($arr, $perPage * ($curr_page - 1), $perPage);

        $paginator = new LengthAwarePaginator($currentItems, count($arr), $perPage, $curr_page, [
            'path' => url('control-panel/users')
        ]);

        $paginator = $paginator->appends('q', $q);

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
    //
    // The admins.dialogs.users_form view, empty; the dialog
    // posts to store itself.
    //
    // Used by:
    //   - users_form() of dialogs.js — the add button of the
    //     accounts page
    // -----------------------------------------------------------

    public function create()
    {
        return view('admins.dialogs.users_form', [
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
    //
    // Creates an active local account with the username,
    // e-mail and password as sent, unless the e-mail or the
    // username is taken; with notif set to 1, mails the
    // password to the new account (SendNewPass). Then to the
    // accounts page with "User created!", or with the error —
    // the accounts page, whatever page an earlier visit left
    // asked for, as for the status switch and the deletion.
    // A form missing the username, e-mail or password, or with
    // an e-mail that is no address or a password shorter than
    // the 8 characters the login form asks for, goes back with
    // the errors.
    //
    // Used by:
    //   - the account dialog, for a new account
    // -----------------------------------------------------------

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|max:255',
            'email' => 'required|email|max:255',
            'password' => 'required|min:8|max:255'
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

                return redirect(route('admin.users'))->withSuccess("User created!");
            } else {
                return redirect(route('admin.users'))->withErrors("Username already registered!");
            }
        } else {
            return redirect(route('admin.users'))->withErrors("Email already registered!");
        }
    }






    // -----------------------------------------------------------
    // edit
    // -----------------------------------------------------------
    //
    // GET /control-panel/dialog/users/edit/{uid}
    //
    // The account dialog showing the account's username and
    // e-mail, for a new password; an unknown account is a 404.
    //
    // Used by:
    //   - users_form() of dialogs.js — the edit buttons of the
    //     accounts page
    // -----------------------------------------------------------

    public function edit(Request $request, $uid)
    {
        return view('admins.dialogs.users_form', [
            'username' => User::findOrFail($uid)->username,
            'email' => User::findOrFail($uid)->email,
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
    // Then to the accounts page with "Updated!". A password
    // shorter than the 8 characters the login form asks for
    // goes back with the error, and an account another admin
    // deleted in the meantime with "No user!", having changed
    // and mailed nothing.
    //
    // Used by:
    //   - the account dialog, for an existing account
    // -----------------------------------------------------------

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|min:8|max:255'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $user = User::find($request->get('uid'));

        if ($user === null) {
            return back()->withErrors(['No user!']);
        }

        User::where(['id' => $request->get('uid')])->update([
            'password' => Hash::make($request->get('password'))
        ]);

        if($request->get('notif') == 1) {
            Mail::to($user->email)->send(new SendNewPass(['new_pass'=>$request->get('password'), 'email'=>$user->email]));
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
    // in the address is a 404. An inactive account can no
    // longer sign in, through the login form or the VU SSO
    // alike. A verification still pending is dropped, so no
    // verification link — old, or new from the login form —
    // switches the account back.
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
        } else {
            abort(404);
        }

        $update = array(
            'status' => $new_status,
            'verify_token' => ''
        );

        User::where(['id' => $user_id])->update($update);

        session()->flash('success', 'Status changed!');

        return redirect(route('admin.users'));
    }






    // -----------------------------------------------------------
    // destroy
    // -----------------------------------------------------------
    //
    // POST /control-panel/users/delete/{uid}
    //
    // Deletes the account and, for a VU account, its group
    // memberships, which are kept by VU id, and its solves and
    // feed rows, so no count, list or leaderboard keeps a
    // player who no longer exists; the players' solve feed is
    // rebuilt without them. The feedback of the account stays.
    //
    // Known bug LAB-48 (low): the account's votes stay in the
    // challenges' thumbs counts, with their Vote_ marks, which
    // hold no direction to take back. Fix: store the direction
    // in the mark and take the votes off here.
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

        ChallengesSubmitted::where(['user_id' => $user_id])->delete();
        ChallengesHistory::where(['user_id' => $user_id])->delete();
        ChallengesHistory::refreshFeed();

        session()->flash('success', 'User deleted!');

        return redirect(route('admin.users'));
    }
}
