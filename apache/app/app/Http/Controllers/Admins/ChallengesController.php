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
//    GET  /control-panel/dialog/challenge_file/new/{uid}           — new-link dialog
//    GET  /control-panel/dialog/challenge_file/edit/{uid}          — edit-link dialog
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
use Illuminate\Support\Facades\DB;
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
use App\Models\ChallengesVotes;
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
//   file_form_new    — the new-link dialog
//   file_form_edit   — the edit-link dialog
//   file_form        — either link dialog
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
    // parameter when one is given — 0 included, and nothing
    // that is not text — and the page links keep q. The view
    // gets the search as settled here for its search box, so
    // a q that is not text shows as no search, and the
    // admin's id, for the edit, delete, Categories and Files
    // links, which a lab admin gets on its own challenges
    // only.
    //
    // Used by:
    //   - the All challenges link of the panel's sidebar
    //   - the list's own search form and its Clear all link
    //   - the redirects of store and update
    // -----------------------------------------------------------

    public function index(Request $request)
    {
        $q = $request->query('q');
        $q = is_string($q) ? $q : '';

        if($q === ''){
            $challenges = Challenges::all()->toArray();
            $challenges_sort = Challenges::all()->sortByDesc('id')->toArray();
        } else {
            $challenges = Challenges::where('name', 'like', '%'.$q.'%')->orWhere('descr', 'like', '%'.$q.'%')->orWhere('content', 'like', '%'.$q.'%')->get()->toArray();
            $challenges_sort = Challenges::where('name', 'like', '%'.$q.'%')->orWhere('descr', 'like', '%'.$q.'%')->orWhere('content', 'like', '%'.$q.'%')->get()->sortByDesc('id')->toArray();
        }

        if (count($challenges) > 0) {
            $arr = $challenges_sort;
        } else {
            $arr = array();
        }

        $curr_page = $this->listPage(count($arr));

        $perPage = 50;

        $currentItems = array_slice($arr, $perPage * ($curr_page - 1), $perPage);

        $paginator = new LengthAwarePaginator($currentItems, count($arr), $perPage, $curr_page, [
            'path' => url('control-panel/challenges')
        ]);

        $paginator = $paginator->appends('q', $q);

        if (count($arr) == 0) {
            $arr = array();
            $paginator = array();
        }
        return view('admins.challenges', [
            'recordlist' => $paginator,
            'user_id'=>Auth::guard('admins')->id(),
            'search' => $q
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
    // with "Created!". A form missing a field, with a name or
    // flag longer than the 255 characters their columns hold,
    // or with points other than a whole number from 0 to
    // 100000 goes back with the errors and what was typed:
    // MySQL, running non-strict, would store a word as 0 and
    // clip a number past its integers, and negative points
    // would take points off every solver.
    //
    // Used by:
    //   - the challenge form, for a new challenge
    // -----------------------------------------------------------

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
            'partner_id' => 'required',
            'descr' => 'required',
            'content' => 'required',
            'answer' => 'required|max:255',
            'points' => 'required|integer|min:0|max:100000'
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
            'name' => $request->input('name'),
            'public_id'=>Str::uuid(),
            'partner_id' => $request->input('partner_id'),
            'created_by'=>Auth::guard('admins')->id(),
            'descr' => $request->input('descr'),
            'content' => $this->clean_content($request->input('content')),
            'answer' => trim($request->input('answer')),
            'points' => $request->input('points'),
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
    // included, posting to update. A challenge whose partner
    // was deleted still points at it, so the partners list
    // gets that id back as "No partner (deleted)", selected:
    // saved again, the challenge stays without a partner,
    // where the browser would otherwise select the first one.
    //
    // Used by:
    //   - the edit links of the challenges list
    // -----------------------------------------------------------

    public function edit(Request $request, $uid)
    {
        $partner_list = Partners::all()->pluck('name', 'id')->toArray();
        $partner_id = Challenges::find($uid)->partner_id;

        if (! array_key_exists($partner_id, $partner_list)) {
            $partner_list = [$partner_id => 'No partner (deleted)'] + $partner_list;
        }

        return view('admins.challenges_form', [
            'url' => env('APP_URL') . '/control-panel/challenges/update',
            'partner_list' => $partner_list,
            'partner_id'=>$partner_id,
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
    // fields — every one required, the name and flag held to
    // 255 characters and the points to a whole number from 0
    // to 100000, as in store, a form that fails going back
    // with the errors and what was typed — with the
    // content through clean_content, then goes to the list
    // with "Updated!". The hidden switch and the author
    // stay as they were, so a lab admin keeps a challenge a
    // main admin corrected. New points count for later solves
    // only: a solve keeps the points it gave, kept with it as
    // points_given.
    //
    // Used by:
    //   - the challenge form, for an existing challenge
    // -----------------------------------------------------------

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
            'partner_id' => 'required',
            'descr' => 'required',
            'content' => 'required',
            'answer' => 'required|max:255',
            'points' => 'required|integer|min:0|max:100000'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        Challenges::where(['id' => $request->input('uid')])->update([
            'name' => $request->input('name'),
            'partner_id' => $request->input('partner_id'),
            'descr' => $request->input('descr'),
            'content' => $this->clean_content($request->input('content')),
            'answer' => $request->input('answer'),
            'points' => $request->input('points')
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
    // solves, votes, categories and group places, then goes
    // back with "Deleted!". The points its solves gave come
    // off the solvers' totals first, as a total is the sum of
    // the player's solves. It all runs in one transaction that
    // first locks the challenge's row: a second delete of the
    // same challenge waits, then finds it gone and goes back
    // with "No challenge!", and an answer being booked
    // finishes before, so its points come off too.
    //
    // Used by:
    //   - Confirm_Dialog() of custom_admin.js — the delete
    //     links of the challenges list
    // -----------------------------------------------------------

    public function destroy($uid)
    {
        $deleted = DB::transaction(function () use ($uid) {
            if (Challenges::whereKey($uid)->lockForUpdate()->first() === null) {
                return false;
            }

            // Summed per player: a database set up before init.sql
            // gained the user_challenge_unique key may hold a solve
            // twice, and a joined update changes each player once
            $solves = DB::table('cyber_challenges_submitted')
                ->where('challenge_id', $uid)
                ->groupBy('user_id')
                ->selectRaw('`user_id`, SUM(`points_given`) AS `given`');

            DB::table('cyber_users')
                ->joinSub($solves, 'solves', 'solves.user_id', '=', 'cyber_users.id')
                ->update(['cyber_users.points' => DB::raw('GREATEST(IFNULL(`cyber_users`.`points`, 0) - `solves`.`given`, 0)')]);

            Challenges::destroy($uid);
            ChallengesHistory::where(['challenge_id'=>$uid])->delete();
            ChallengesFiles::where(['challenge_id'=>$uid])->delete();
            ChallengesSubmitted::where(['challenge_id'=>$uid])->delete();
            ChallengesCategories::where(['challenge_id'=>$uid])->delete();
            GroupsChallenges::where(['challenge_id'=>$uid])->delete();
            ChallengesVotes::where(['challenge_id'=>$uid])->delete();

            return true;
        });

        if (! $deleted) {
            return back()->withErrors(['No challenge!']);
        }

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
    // categories_store. The route's r_exists check
    // (challenge_owned) keeps a lab admin to the dialogs of
    // its own challenges, as for saving them, and answers an
    // unknown challenge with "No challenge!".
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
    // field at all, and the challenge is left with none. An
    // item field that is not a list — only a hand-made request
    // sends one — goes back with the error and changes
    // nothing, and a key that names no category is passed
    // over. The replacement runs in a transaction that first
    // locks the challenge's row, so a save sent twice at once
    // — a double click — waits for the other and stores each
    // category once, and a challenge deleted meanwhile goes
    // back with "No challenge!".
    //
    // Used by:
    //   - the categories dialog's form
    // -----------------------------------------------------------

    public function categories_store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $items = $request->input('item') ?? [];
        $known = Categories::whereIn('id', array_keys($items))->pluck('id')->all();

        $saved = DB::transaction(function () use ($request, $items, $known) {
            if (Challenges::whereKey($request->input('uid'))->lockForUpdate()->first() === null) {
                return false;
            }

            ChallengesCategories::where(['challenge_id'=>$request->input('uid')])->delete();

            foreach($items as $k => $v){
                if (in_array($k, $known)) {
                    ChallengesCategories::create([
                        'cat_id' => $k,
                        'challenge_id' => $request->input('uid')
                    ]);
                }
            }

            return true;
        });

        if (! $saved) {
            return back()->withErrors(['No challenge!']);
        }

        if(count($items)>0){
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
    // links, 50 to a page with the page links under them,
    // under the challenge's name. The page prints the id into
    // its add button's script, which is safe as the route
    // takes a plain number only (RouteServiceProvider::boot).
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

        $curr_page = $this->listPage(count($arr));

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
    // file_form_new
    // -----------------------------------------------------------
    //
    // GET /control-panel/dialog/challenge_file/new/{uid}
    //
    // file_form for a new link of the challenge uid. The
    // route's r_exists check (challenge_owned) keeps a lab
    // admin to its own challenges.
    //
    // Used by:
    //   - challenge_files_form() of dialogs.js — the add
    //     button of the file links page
    // -----------------------------------------------------------

    public function file_form_new(Request $request, $uid)
    {
        return $this->file_form($request, 'new', $uid);
    }






    // -----------------------------------------------------------
    // file_form_edit
    // -----------------------------------------------------------
    //
    // GET /control-panel/dialog/challenge_file/edit/{uid}
    //
    // file_form for the link uid. The route's r_exists check
    // (file_owned) keeps a lab admin to the links of its own
    // challenges.
    //
    // Used by:
    //   - challenge_files_form() of dialogs.js — the edit
    //     buttons of the file links page
    // -----------------------------------------------------------

    public function file_form_edit(Request $request, $uid)
    {
        return $this->file_form($request, 'edit', $uid);
    }






    // -----------------------------------------------------------
    // file_form
    // -----------------------------------------------------------
    //
    // The admins.dialogs.challenge_files_form view: for new,
    // an empty link of the challenge uid, posting to
    // file_store; for edit, the link uid, posting to
    // file_update — a link that does not exist is a 404, and a
    // link of a challenge the admin may not change goes back
    // with "You don't have permissions to do this!", as the
    // route's check lets a missing link through and another
    // admin may create it in the meantime.
    //
    // Used by:
    //   - file_form_new and file_form_edit (above)
    // -----------------------------------------------------------

    private function file_form(Request $request, $type, $uid)
    {
        if($type =='new'){
            $url = env('APP_URL')."/control-panel/challenges_file/store";
            $name = '';
            $file_url = '';
        } elseif($type =='edit'){
            $file = ChallengesFiles::findOrFail($uid);

            // The route's check let a missing link through, which
            // another admin may have created since
            if (! $this->mayChangeChallenge($file->challenge_id)) {
                return back()->withErrors(['You don\'t have permissions to do this!']);
            }

            $url = env('APP_URL')."/control-panel/challenges_file/update";
            $name = $file->name;
            $file_url = $file->file_url;
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
    // url rule knows out. The name and address stored are the
    // ones the rules checked, which validate() hands back.
    //
    // Used by:
    //   - the link dialog, for a new link
    // -----------------------------------------------------------

    public function file_store(Request $request)
    {
        $checked = $request->validate([
            'name' => 'required|string|max:255',
            'file_url' => 'required|url:http,https|starts_with:http://,https://|max:2048',
        ]);

        ChallengesFiles::create([
            'challenge_id'=>$request->input('uid'),
            'name'=>$checked['name'],
            'file_url'=>$checked['file_url']
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
    // Changes the link with the posted uid — one whole number,
    // as Laravel would check a list of ids by any of them and
    // change only the first — under the rules of file_store
    // and with the values they checked, then goes back with
    // "File updated!". The route's r_exists check
    // (file_owned_uid) keeps a lab admin to the links of its
    // own challenges and lets an unknown link through, so a
    // link another admin deleted in the meantime is turned
    // back here, with "No file!", and one another admin
    // created in the meantime is checked here again and
    // refused unless the admin may change its challenge.
    //
    // Used by:
    //   - the link dialog, for an existing link
    // -----------------------------------------------------------

    public function file_update(Request $request)
    {
        // One number: a list of ids would be checked by any of
        // them and change only the first
        $checked = $request->validate([
            'uid' => 'required|integer',
            'name' => 'required|string|max:255',
            'file_url' => 'required|url:http,https|starts_with:http://,https://|max:2048',
        ]);

        $challenge_id = ChallengesFiles::whereKey($checked['uid'])->value('challenge_id');

        if ($challenge_id === null) {
            return back()->withErrors(['No file!']);
        }

        if (! $this->mayChangeChallenge($challenge_id)) {
            return back()->withErrors(['You don\'t have permissions to do this!']);
        }

        ChallengesFiles::where(['id'=>$checked['uid']])->update([
            'name'=>$checked['name'],
            'file_url'=>$checked['file_url']]);

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
    // the links of its own challenges, and lets a link already
    // gone through, as deleting it again changes nothing; a
    // link another admin created with that id in the meantime
    // is checked here again and left alone unless the admin
    // may change its challenge.
    //
    // Used by:
    //   - Confirm_Dialog() of custom_admin.js — the delete
    //     links of the file links page
    // -----------------------------------------------------------

    public function file_destroy($uid)
    {
        $challenge_id = ChallengesFiles::whereKey($uid)->value('challenge_id');

        if ($challenge_id !== null) {
            if (! $this->mayChangeChallenge($challenge_id)) {
                return back()->withErrors(['You don\'t have permissions to do this!']);
            }

            ChallengesFiles::destroy($uid);
        }

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
    // shows it, any other value is a 404 — and goes back; the
    // players' solve feed lists visible challenges only.
    // Main-only.
    //
    // Used by:
    //   - Post_Link() of custom_admin.js — the Visible and
    //     Hidden switches of the challenges list
    // -----------------------------------------------------------

    public function status(Request $request, $status, $challenge_id)
    {
        if (! in_array($status, ['0', '1'], true)) {
            abort(404);
        }

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
