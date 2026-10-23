<?php

// -----------------------------------------------------------
//  [*] ChallengesController — the challenges, as admins keep
//      them
//
//  The control panel's challenges: the list, the challenge
//  form, a challenge's categories and file links, and the
//  switch that hides one from the main dashboard. Every
//  admin sees every challenge, but changes only those they
//  created — the r_exists middleware checks — while a main
//  admin changes them all. A lab admin's new challenge
//  starts hidden, for the groups; a main admin's shows on
//  the dashboard. A lab admin's content is cleaned of
//  scripts before it is stored, as the players' challenge
//  page prints it as HTML.
//
//    GET  /control-panel/challenges                                — the list
//    GET  /control-panel/challenges/create                         — the empty form
//    POST /control-panel/challenges/store                          — adds
//    GET  /control-panel/challenges/edit/{uid}                     — the filled form
//    POST /control-panel/challenges/update                         — changes
//    POST /control-panel/challenges/delete/{uid}                   — deletes
//    GET  /control-panel/dialog/challenges/categories/{uid}        — categories dialog
//    POST /control-panel/challenges/categories/store               — sets categories
//    GET  /control-panel/challenges/files/{uid}                    — the file links
//    GET  /control-panel/dialog/challenge_file/{type}/{uid}        — link dialog
//    POST /control-panel/challenges_file/store                     — adds a link
//    POST /control-panel/challenges_file/update                    — changes a link
//    POST /control-panel/challenges/files/delete/{uid}             — deletes a link
//    POST /control-panel/challenges/status/{status}/{challenge_id} — hides or shows
//
//  Used by:
//    - routes/web.php — the routes above
// -----------------------------------------------------------


namespace App\Http\Controllers\Admins;

use Session;
use App\Http\Controllers\Admins\BaseController;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use HTMLPurifier;
use HTMLPurifier_Config;
use App\Models\Partners;
use App\Models\Categories;
use App\Models\Challenges;
use App\Models\ChallengesHistory;
use App\Models\ChallengesFiles;
use App\Models\ChallengesCategories;
use App\Models\ChallengesSubmitted;
use App\Models\GroupsChallenges;








// -----------------------------------------------------------
// ChallengesController
// -----------------------------------------------------------
//
//   __construct      — the base's layout data
//   index            — the challenges list
//   create           — the empty form
//   store            — adds a challenge
//   edit             — the filled form
//   update           — changes a challenge
//   destroy          — deletes a challenge
//   categories       — the categories dialog
//   categories_store — sets a challenge's categories
//   file_list        — a challenge's file links
//   file_form        — the link dialog
//   file_store       — adds a link
//   file_update      — changes a link
//   file_destroy     — deletes a link
//   status           — hides or shows a challenge
//   clean_content    — a lab admin's content, made safe
//
// Used by:
//   - routes/web.php
// -----------------------------------------------------------

class ChallengesController extends BaseController
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
    // GET /control-panel/challenges
    //
    // The admins.challenges view: every challenge, newest
    // first, 50 to a page of the list, narrowed to those whose
    // name, description or content contains the q query
    // parameter when one is given; the page links keep q. The
    // view gets the admin's id too, to offer changes on the
    // admin's own challenges only.
    //
    // Used by:
    //   - the Challenges link of the panel's sidebar, and the
    //     redirects of store and update
    // -----------------------------------------------------------

    public function index(Request $request)
    {
        if(empty($request->query('q'))){
            $challenges = Challenges::all()->toArray();
            $challenges_sort = Challenges::all()->sortByDesc('id')->toArray();
        } else {
            $challenges = Challenges::where('name', 'like', '%'.$request->query('q').'%')->orWhere('descr', 'like', '%'.$request->query('q').'%')->orWhere('content', 'like', '%'.$request->query('q').'%')->get()->toArray();
            $challenges_sort = Challenges::where('name', 'like', '%'.$request->query('q').'%')->orWhere('descr', 'like', '%'.$request->query('q').'%')->orWhere('content', 'like', '%'.$request->query('q').'%')->get()->sortByDesc('id')->toArray();
        }

        if (count($challenges) > 0) {
            $arr = $challenges_sort;
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
            'path' => url('control-panel/challenges')
        ]);

        $paginator = $paginator->appends('q', $request->query('q'));

        if (count($arr) == 0) {
            $arr = array();
            $paginator = array();
        }
        return view('admins.challenges', [
            'recordlist' => $paginator,
            'user_id'=>Auth::guard('admins')->id()
        ]);
    }






    // -----------------------------------------------------------
    // create
    // -----------------------------------------------------------
    //
    // GET /control-panel/challenges/create
    //
    // The admins.challenges_form view, empty, posting to store,
    // with the partners to choose from.
    //
    // Used by:
    //   - the add button of the challenges list
    // -----------------------------------------------------------

    public function create()
    {
        return view('admins.challenges_form', [
            'url' => env('APP_URL') . '/control-panel/challenges/store',
            'partner_list' => Partners::all()->pluck('name', 'id')->toArray(),
            'partner_id'=>'',
            'name' => '',
            'descr' => '',
            'content' => '',
            'answer' => '',
            'points' => '',
            'uid'=>''
        ]);
    }






    // -----------------------------------------------------------
    // store
    // -----------------------------------------------------------
    //
    // POST /control-panel/challenges/store
    //
    // Adds a challenge owned by the signed-in admin, with a
    // fresh UUID as its public id, the flag trimmed and the
    // content through clean_content; hidden unless the
    // admin's account is a main admin's. Then to the list
    // with "Created!". A form missing a field goes back with
    // the errors and what was typed.
    //
    // Used by:
    //   - the challenge form, for a new challenge
    // -----------------------------------------------------------

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'partner_id' => 'required',
            'descr' => 'required',
            'content' => 'required',
            'answer' => 'required',
            'points' => 'required'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        if(Auth::guard('admins')->user()?->main == 1){
            $hidden=0;
        } else {
            $hidden=1;
        }

        Challenges::create([
            'name' => $request->get('name'),
            'public_id'=>Str::uuid(),
            'partner_id' => $request->get('partner_id'),
            'created_by'=>Auth::guard('admins')->id(),
            'descr' => $request->get('descr'),
            'content' => $this->clean_content($request->get('content')),
            'answer' => trim($request->get('answer')),
            'points' => $request->get('points'),
            'hidden' => $hidden
        ]);

        session()->flash('success', 'Created!');

        return redirect(route('admin.challenges'));
    }






    // -----------------------------------------------------------
    // edit
    // -----------------------------------------------------------
    //
    // GET /control-panel/challenges/edit/{uid}
    //
    // The challenge form filled with the challenge, its flag
    // included, posting to update.
    //
    // Used by:
    //   - the edit links of the challenges list
    // -----------------------------------------------------------

    public function edit(Request $request, $uid)
    {
        return view('admins.challenges_form', [
            'url' => env('APP_URL') . '/control-panel/challenges/update',
            'partner_list' => Partners::all()->pluck('name', 'id')->toArray(),
            'partner_id'=>Challenges::find($uid)->partner_id,
            'name' => Challenges::find($uid)->name,
            'descr' => Challenges::find($uid)->descr,
            'content' => Challenges::find($uid)->content,
            'answer' => Challenges::find($uid)->answer,
            'points' => Challenges::find($uid)->points,
            'uid' => $uid
        ]);
    }






    // -----------------------------------------------------------
    // update
    // -----------------------------------------------------------
    //
    // POST /control-panel/challenges/update
    //
    // Changes the challenge with the posted uid to the form's
    // fields as sent — the content through clean_content, as
    // in store — then goes to the list with "Updated!". The
    // hidden switch and the author stay as they were, so a lab
    // admin keeps a challenge a main admin corrected.
    //
    // Used by:
    //   - the challenge form, for an existing challenge
    // -----------------------------------------------------------

    public function update(Request $request)
    {
        Challenges::where(['id' => $request->get('uid')])->update([
            'name' => $request->get('name'),
            'partner_id' => $request->get('partner_id'),
            'descr' => $request->get('descr'),
            'content' => $this->clean_content($request->get('content')),
            'answer' => $request->get('answer'),
            'points' => $request->get('points')
        ]);

        session()->flash('success', 'Updated!');

        return redirect(route('admin.challenges'));
    }






    // -----------------------------------------------------------
    // destroy
    // -----------------------------------------------------------
    //
    // POST /control-panel/challenges/delete/{uid}
    //
    // Deletes the challenge with its feed rows, file links,
    // solves, categories and group places, then goes back with
    // "Deleted!". The players keep the points its solves gave.
    //
    // Used by:
    //   - Confirm_Dialog() of custom_admin.js — the delete
    //     links of the challenges list
    // -----------------------------------------------------------

    public function destroy($uid)
    {
        Challenges::destroy($uid);
        ChallengesHistory::where(['challenge_id'=>$uid])->delete();
        ChallengesFiles::where(['challenge_id'=>$uid])->delete();
        ChallengesSubmitted::where(['challenge_id'=>$uid])->delete();
        ChallengesCategories::where(['challenge_id'=>$uid])->delete();
        GroupsChallenges::where(['challenge_id'=>$uid])->delete();

        session()->flash('success', 'Deleted!');

        return back();
    }






    // -----------------------------------------------------------
    // categories
    // -----------------------------------------------------------
    //
    // GET /control-panel/dialog/challenges/categories/{uid}
    //
    // The admins.dialogs.challenges_categories view: every
    // category, the challenge's ticked, posting to
    // categories_store. The route checks no ownership, so any
    // admin may open the dialog of any challenge; saving it is
    // checked.
    //
    // Used by:
    //   - challenge_categories() of dialogs.js — the category
    //     buttons of the challenges list
    // -----------------------------------------------------------

    public function categories(Request $request, $uid)
    {
        return view('admins.dialogs.challenges_categories', [
            'url' => env('APP_URL') . '/control-panel/challenges/categories/store',
            'categories_list' => Categories::all()->pluck('name', 'id')->toArray(),
            'selected_categories'=> ChallengesCategories::where(['challenge_id'=>$uid])->get()->pluck('cat_id', 'id')->toArray(),
            'challenge_name'=> Challenges::find($uid)->name,
            'uid'=>$uid
        ]);
    }






    // -----------------------------------------------------------
    // categories_store
    // -----------------------------------------------------------
    //
    // POST /control-panel/challenges/categories/store
    //
    // Replaces the categories of the challenge with the posted
    // uid by the ticked ones — the keys of the item field —
    // then goes back, with "Categoried updated!" when any was
    // ticked. With every box unticked the form sends no item
    // field at all, and the challenge is left with none.
    //
    // Used by:
    //   - the categories dialog's form
    // -----------------------------------------------------------

    public function categories_store(Request $request)
    {
        ChallengesCategories::where(['challenge_id'=>$request->get('uid')])->delete();

        $items = $request->get('item', []);

        if(count($items)>0){
            foreach($items as $k => $v){
                ChallengesCategories::create([
                    'cat_id' => $k,
                    'challenge_id' => $request->get('uid')
                ]);
            }

            session()->flash('success', 'Categoried updated!');
        }

        return back();
    }






    // -----------------------------------------------------------
    // file_list
    // -----------------------------------------------------------
    //
    // GET /control-panel/challenges/files/{uid}
    //
    // The admins.challenges_files view: the challenge's file
    // links, 50 to a page of the list, under the challenge's
    // name.
    //
    // Used by:
    //   - the files links of the challenges list
    // -----------------------------------------------------------

    public function file_list(Request $request, $uid)
    {
        if (count(ChallengesFiles::where(['challenge_id'=>$uid])->get()->toArray()) > 0) {
            $arr = ChallengesFiles::where(['challenge_id'=>$uid])->get()->toArray();
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
            'path' => url('control-panel/challenges/files/'.$uid)
        ]);

        if (count($arr) == 0) {
            $arr = array();
            $paginator = array();
        }
        return view('admins.challenges_files', [
            'challenge_name'=>Challenges::find($uid)->name,
            'recordlist' => $paginator,
            'uid'=>$uid
        ]);
    }






    // -----------------------------------------------------------
    // file_form
    // -----------------------------------------------------------
    //
    // GET /control-panel/dialog/challenge_file/{type}/{uid}
    //
    // The admins.dialogs.challenge_files_form view: for new,
    // an empty link of the challenge uid, posting to
    // file_store; for edit, the link uid, posting to
    // file_update. Any other type leaves the address undefined
    // and ends in a server error. The route checks no
    // ownership.
    //
    // Used by:
    //   - challenge_files_form() of dialogs.js — the add and
    //     edit buttons of the file links page
    // -----------------------------------------------------------

    public function file_form(Request $request, $type, $uid)
    {
        if($type =='new'){
            $url = env('APP_URL')."/control-panel/challenges_file/store";
            $name = '';
            $file_url = '';
        } elseif($type =='edit'){
            $url = env('APP_URL')."/control-panel/challenges_file/update";
            $name = ChallengesFiles::find($uid)->name;
            $file_url = ChallengesFiles::find($uid)->file_url;
        }

        return view('admins.dialogs.challenge_files_form', [
            'type'=>$type,
            'uid' =>$uid,
            'url'=>$url,
            'name'=>$name,
            'file_url'=>$file_url
        ]);
    }






    // -----------------------------------------------------------
    // file_store
    // -----------------------------------------------------------
    //
    // POST /control-panel/challenges_file/store
    //
    // Adds a link to the challenge with the posted uid — a
    // name of up to 255 characters and a web address of up to
    // 2048 — then goes back with "File created!". The url rule
    // of Laravel 10.6 ignores its protocol list, so starts_with
    // is what keeps ftp, smb, file and the other schemes the
    // url rule knows out.
    //
    // Used by:
    //   - the link dialog, for a new link
    // -----------------------------------------------------------

    public function file_store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'file_url' => 'required|url:http,https|starts_with:http://,https://|max:2048',
        ]);

        ChallengesFiles::create([
            'challenge_id'=>$request->get('uid'),
            'name'=>$request->get('name'),
            'file_url'=>$request->get('file_url')
        ]);

        session()->flash('success', 'File created!');

        return back();
    }






    // -----------------------------------------------------------
    // file_update
    // -----------------------------------------------------------
    //
    // POST /control-panel/challenges_file/update
    //
    // Changes the link with the posted uid, under the rules of
    // file_store, then goes back with "File updated!". The
    // route's r_exists check (file_owned_uid) keeps a lab
    // admin to the links of its own challenges.
    //
    // Used by:
    //   - the link dialog, for an existing link
    // -----------------------------------------------------------

    public function file_update(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'file_url' => 'required|url:http,https|starts_with:http://,https://|max:2048',
        ]);

        ChallengesFiles::where(['id'=>$request->get('uid')])->update([
            'name'=>$request->get('name'),
            'file_url'=>$request->get('file_url')]);

        session()->flash('success', 'File updated!');

        return back();
    }






    // -----------------------------------------------------------
    // file_destroy
    // -----------------------------------------------------------
    //
    // POST /control-panel/challenges/files/delete/{uid}
    //
    // Deletes the link and goes back with "File deleted!". The
    // route's r_exists check (file_owned) keeps a lab admin to
    // the links of its own challenges.
    //
    // Used by:
    //   - Confirm_Dialog() of custom_admin.js — the delete
    //     links of the file links page
    // -----------------------------------------------------------

    public function file_destroy($uid)
    {
        ChallengesFiles::destroy($uid);

        session()->flash('success', 'File deleted!');

        return back();
    }






    // -----------------------------------------------------------
    // status
    // -----------------------------------------------------------
    //
    // POST /control-panel/challenges/status/{status}/{challenge_id}
    //
    // Writes the status from the address into the challenge's
    // hidden column — 1 hides it from the main dashboard, 0
    // shows it — and goes back. Main-only.
    //
    // Used by:
    //   - Post_Link() of custom_admin.js — the Visible and
    //     Hidden switches of the challenges list
    // -----------------------------------------------------------

    public function status(Request $request, $status, $challenge_id)
    {
        Challenges::where(['id'=>$challenge_id])->update(['hidden'=>$status]);

        return back();
    }






    // -----------------------------------------------------------
    // clean_content
    // -----------------------------------------------------------
    //
    // The content a challenge form sent, made safe to print as
    // HTML on the players' challenge page when a lab admin
    // wrote it: HTMLPurifier keeps the formatting the editor
    // produces — text styles, lists, tables, links, pictures,
    // pasted ones included — and drops scripts, event
    // handlers, frames and javascript: links. A main admin's
    // content, and a missing one, are kept as sent: main
    // admins are trusted with the whole site already. The
    // definition cache stays off, as the container's file
    // system is read-only.
    //
    // Used by:
    //   - store and update (above)
    // -----------------------------------------------------------

    private function clean_content($content)
    {
        if ($content === null || Auth::guard('admins')->user()?->main == 1) {
            return $content;
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Cache.DefinitionImpl', null);

        // The editor embeds a pasted picture as a data: address,
        // and its links may open in a new tab
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'data' => true]);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);

        return (new HTMLPurifier($config))->purify($content);
    }
}
