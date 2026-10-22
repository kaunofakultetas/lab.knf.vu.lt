<?php

// -----------------------------------------------------------
//  [*] ChallengesController — the challenges, as players see
//      them
//
//  The heart of the lab: the dashboard of challenges, a
//  challenge's page, the answer form that books a solve
//  and its points, the thumbs-up and thumbs-down votes kept
//  in Redis, and the feedback dialog. Every route asks for
//  a signed-in player.
//
//    GET  /user/dashboard                                — the dashboard
//    GET  /user/challenge/{public_id}/{public_group_id?} — a challenge
//    POST /user/challenge/{public_id}/{public_group_id?} — an answer
//    GET  /user/challenge/vote/{type}/{public_id}        — a vote
//    GET  /user/dialog/feedback                          — the dialog
//    POST /user/feedback                                 — feedback
//
//  Used by:
//    - routes/web.php — the routes above
// -----------------------------------------------------------


namespace App\Http\Controllers\Users;

use App\Http\Controllers\Users\BaseController;
use App\Http\Controllers\Users\SettingsController;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\Groups;
use App\Models\User;
use App\Models\Feedback;
use App\Models\Partners;
use App\Models\Categories;
use App\Models\Challenges;
use App\Models\ChallengesHistory;
use App\Models\ChallengesFiles;
use App\Models\ChallengesCategories;
use App\Models\ChallengesSubmitted;








// -----------------------------------------------------------
// ChallengesController
// -----------------------------------------------------------
//
//   __construct     — the base's layout data
//   index           — the dashboard
//   show            — a challenge's page
//   submit_answer   — an answer, and a solve when it is right
//   vote            — a thumbs-up or thumbs-down
//   dialog_feedback — the feedback dialog
//   feedback_save   — stores feedback
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
    // GET /user/dashboard
    //
    // The users.dashboard view: every challenge not hidden,
    // unsolved ones first and by points within each half, with
    // whether the player solved it, its category names and
    // category ids — the classes the page's category filter
    // reads — and its votes. The sorting helper is declared
    // inside the action, so the action runs once per PHP
    // process: Apache serves each request afresh, and the
    // tests render these pages in separate processes.
    //
    // Used by:
    //   - the Dashboard link of the player top bar, the end of
    //     every login, and the redirects of the challenge pages
    // -----------------------------------------------------------

    public function index(Request $request)
    {
        // The visible challenges, with the player's solves, the
        // categories and the votes
        if (count(Challenges::where(['hidden'=>0])->orderby('points')->get()->toArray()) > 0) {
            $arr = Challenges::where(['hidden'=>0])->orderby('points')->get()->toArray();

            foreach ($arr as $k => $v) {
                $arr[$k]['public_id'] = $v['public_id'];

                $arr[$k]['solved'] = ChallengesSubmitted::where('user_id', '=', Auth::guard('users')->id())->where('challenge_id', '=', $v['id'])->count();

                $arr[$k]['categories'] = implode(", ", ChallengesCategories::
                    where(['challenge_id' => $arr[$k]['id']])
                    ->leftJoin('cyber_categories', 'cyber_categories.id', '=', 'cyber_challenges_categories.cat_id')
                    ->get()->pluck('name')->toArray());

                $arr[$k]['categories_ids'] = implode(" c", ChallengesCategories::
                    where(['challenge_id' => $arr[$k]['id']])
                    ->leftJoin('cyber_categories', 'cyber_categories.id', '=', 'cyber_challenges_categories.cat_id')
                    ->get()->pluck('id')->toArray());

                $arr[$k]['thumbs_up_count'] = Redis::get('ThumbsUp_'.$v['public_id']) ?? 0;
                $arr[$k]['thumbs_down_count'] = Redis::get('ThumbsDown_'.$v['public_id']) ?? 0;
                $arr[$k]['voted'] = Redis::get('Vote_'.$v['public_id'].'_'.Auth::guard('users')->id()) ?? 0;
            }
        } else {
            $arr = array();
        }






        // -----------------------------------------------------------
        // aasort
        // -----------------------------------------------------------
        //
        // Sorts the rows of an array by one key, ascending,
        // keeping their keys; asort is stable on PHP 8, so rows
        // with equal values keep their order. Declared inside
        // index, it exists once index has run, and a second run
        // in the same PHP process fails on the redeclared
        // function.
        //
        // Used by:
        //   - index (around it) — unsolved challenges first
        // -----------------------------------------------------------

        function aasort(&$array, $key)
        {
            $sorter = array();
            $ret = array();
            reset($array);
            foreach ($array as $ii => $va) {
                $sorter[$ii] = $va[$key];
            }
            asort($sorter);
            foreach ($sorter as $ii => $va) {
                $ret[$ii] = $array[$ii];
            }
            $array = $ret;
        }

        aasort($arr, "solved");

        return view('users.dashboard', [
            'recordlist' => $arr,
            'categories' => Categories::all()->pluck('name', 'id')->toArray()
        ]);
    }






    // -----------------------------------------------------------
    // show
    // -----------------------------------------------------------
    //
    // GET /user/challenge/{public_id}/{public_group_id?}
    //
    // The users.challenge view: the challenge — its content
    // printed as HTML (LAB-29) — its partner, file links and
    // votes. An unknown challenge goes back to the dashboard
    // with "Challenge doesn't exists!", an unknown group back
    // with "Error!". The group only has to exist: neither the
    // membership nor the challenge's place in the group is
    // checked, and a hidden challenge opens like any other.
    // The validate.ifsolved middleware has sent solved ones to
    // the dashboard already.
    //
    // Used by:
    //   - the challenge cards of the dashboard and of a
    //     group's page
    // -----------------------------------------------------------

    public function show(Request $request, $public_id, $public_group_id=NULL)
    {
        $validator = Validator::make(['public_id'=>$public_id], [
            'public_id' => ['required', 'exists:cyber_challenges,public_id']
        ]);

        if ($validator->fails()) {
            return redirect(route('user_dashboard'))->withErrors(['Challenge doesn\'t exists!']);
        }

        if($public_group_id != NULL){
            if(Groups::groupExists($public_group_id) == false){
                return back()->withErrors(['Error!']);
            }
        }

        $files = ChallengesFiles::getFilesById(Challenges::getIdByPublicId($public_id));

        return view('users.challenge', [
            'data'=>Challenges::where(['public_id'=>$public_id])->get()->first(),
            'partner'=>Partners::where(['id'=>Challenges::getPartnerIdByPublicId($public_id)])->get()->first(),
            'files'=>$files,
            'public_id' => $public_id,
            'public_group_id' => $public_group_id,
            'thumbs_up_count'=>Redis::get('ThumbsUp_'.$public_id) ?? 0,
            'thumbs_down_count'=>Redis::get('ThumbsDown_'.$public_id) ?? 0,
            'voted'=>Redis::get('Vote_'.$public_id.'_'.Auth::guard('users')->id()) ?? 0
        ]);
    }






    // -----------------------------------------------------------
    // submit_answer
    // -----------------------------------------------------------
    //
    // POST /user/challenge/{public_id}/{public_group_id?}
    //
    // Checks an answer and books a right one: the solve with
    // its points and address, the points added to the player,
    // a row of the solve feed and a fresh tasks_history in
    // Redis; then back to the dashboard — or the group's page
    // — with the points won. Every "VU{" and "}" is removed
    // from the answer before the compare, so the flag matches
    // with or without its wrapper — and a stored flag must be
    // saved without it. Wrong answers and solved challenges
    // go back with "Wrong answer" or "Already solved".
    //
    // Known bug LAB-04 (low): the compare is ==
    // (ChallengesController.php:331), which PHP makes numeric
    // for numeric strings, so a numeric flag is also accepted
    // in any spelling of the same number. Fix: compare with
    // ===.
    //
    // Known bug LAB-21 (low): answers have no rate limit
    // (routes/web.php), so flags can be guessed without end.
    // Fix: throttle the route per player.
    //
    // Known bug LAB-22 (low): the points are read, added in PHP
    // and written back (ChallengesController.php:340), so a
    // solve booked by another tab between the read and the
    // write is lost. Fix: add the points in SQL, with
    // increment().
    //
    // Known bug LAB-24 (low): the same right answer sent twice
    // at once passes both "already solved" checks; the unique
    // key then refuses the second insert, whose QueryException
    // ends on the 500 page. Fix: catch the duplicate and answer
    // as for a solved challenge.
    //
    // Used by:
    //   - the answer form of users/challenge.blade.php
    // -----------------------------------------------------------

    public function submit_answer(Request $request, $public_id, $public_group_id=NULL)
    {
        // STEP 1: the challenge and the group must exist
        // ==============================================
        $validator = Validator::make(['public_id'=>$public_id], [
            'public_id' => ['required', 'exists:cyber_challenges,public_id']
        ]);

        if ($validator->fails()) {
            return redirect(route('user_dashboard'))->withErrors(['Challenge doesn\'t exists!']);
        }

        if($public_group_id != NULL){
            if(Groups::groupExists($public_group_id) == false){
                return back()->withErrors(['Error!']);
            }
        }


        // STEP 2: the answer without its wrapper, and the
        // challenge's flag and points
        // ===============================================
        $answer = trim(str_replace("VU{","",str_replace("}","",$request->get('answer'))));

        $challenge_id = Challenges::getIdByPublicId($public_id);

        $challenge_answer = Challenges::where(['public_id' => $public_id])->get()->pluck('answer')->first();

        $challenge_points = Challenges::where(['public_id' => $public_id])->get()->pluck('points')->first();

        $solved = ChallengesSubmitted::where('user_id', '=', Auth::guard('users')->id())->where('challenge_id', '=', $challenge_id)->count();


        // STEP 3: a right answer books the solve, the points, the
        // feed row and the cached feed
        // =======================================================
        if ($solved == 0) {
            if (trim($challenge_answer) == $answer) {
                $submitTask = new ChallengesSubmitted();
                $submitTask->user_id = Auth::guard('users')->id();
                $submitTask->challenge_id = $challenge_id;
                $submitTask->points_given = $challenge_points;
                $submitTask->created = date('Y-m-d H:i:s');
                $submitTask->ip = SettingsController::getUserIP();
                $submitTask->save();

                User::where(["id" => Auth::guard('users')->id()])->update(
                    ["points" => User::select('points')->where(
                        ["id" => Auth::guard('users')->id()])->get()->pluck('points')->first() + $challenge_points]);

                // The content line is neither fillable nor a column,
                // so the model drops it
                ChallengesHistory::create([
                    'user_id'=>Auth::guard('users')->id(),
                    'challenge_id'=>$challenge_id,
                    'content' => User::find(Auth::guard('users')->id())->getUsername() . ' solved ' . Challenges::find($challenge_id)->name . ' challenge',
                    'created_time' => time(),
                    'created' => date('Y-m-d H:i:s')
                ]);

                // The six latest solves, with names, for the sidebar
                Redis::set('tasks_history', json_encode(
                    ChallengesHistory::selectRaw("cyber_challenges_history.*, cyber_users.username, cyber_challenges.name")
                    ->leftJoin('cyber_users', 'cyber_users.id', 'cyber_challenges_history.user_id')
                    ->leftJoin('cyber_challenges', 'cyber_challenges.id', 'cyber_challenges_history.challenge_id')
                    ->orderBy('id', 'desc')
                    ->take(6)
                    ->get()
                    ->toArray()));

                if($public_group_id == NULL){
                    return redirect()->intended(route('user_dashboard'))->withSuccess("Your answer is correct! You received $challenge_points points");
                } else {
                    return redirect('/user/group/'.$public_group_id)->withSuccess("Your answer is correct! You received $challenge_points points");
                }
            } else {
                return back()->withErrors(['Wrong answer']);
            }
        } else {
            return back()->withErrors(['Already solved']);
        }
    }






    // -----------------------------------------------------------
    // vote
    // -----------------------------------------------------------
    //
    // GET /user/challenge/vote/{type}/{public_id}
    //
    // One vote per player and challenge: up or down adds one
    // to the challenge's ThumbsUp_ or ThumbsDown_ counter in
    // Redis, and any type marks the player as having voted.
    // The answer is an empty page; the challenge is never
    // looked up, so any public id collects votes.
    //
    // Known bug LAB-23 (low): the counter is read, added to in
    // PHP and written back (ChallengesController.php:410), so
    // two votes at the same moment can count as one. Fix:
    // Redis INCR.
    //
    // Used by:
    //   - thumbs() and thumbs_list() of custom.js — the vote
    //     buttons of the dashboard and the challenge page
    // -----------------------------------------------------------

    public function vote(Request $req, $type, $public_id)
    {
        $voted = Redis::get('Vote_'.$public_id.'_'.Auth::guard('users')->id()) ?? 0;

        if($voted == 0){
            if($type == 'up'){
                $current = Redis::get('ThumbsUp_'.$public_id) ?? 0;

                Redis::set('ThumbsUp_'.$public_id, $current + 1);
            }

            if($type == 'down'){
                $current = Redis::get('ThumbsDown_'.$public_id) ?? 0;

                Redis::set('ThumbsDown_'.$public_id, $current + 1);
            }

            Redis::set('Vote_'.$public_id.'_'.Auth::guard('users')->id(), 1);
        }
    }






    // -----------------------------------------------------------
    // dialog_feedback
    // -----------------------------------------------------------
    //
    // GET /user/dialog/feedback
    //
    // The users.dialogs.feedback view, loaded into a dialog.
    //
    // Used by:
    //   - feedback() of custom.js — the Feedback link of the
    //     player footer
    // -----------------------------------------------------------

    public function dialog_feedback()
    {
        return view('users.dialogs.feedback', []);
    }






    // -----------------------------------------------------------
    // feedback_save
    // -----------------------------------------------------------
    //
    // POST /user/feedback
    //
    // Stores the feedback text as given, then goes back with
    // "Thank you for your feedback!".
    //
    // Used by:
    //   - the form of the feedback dialog
    // -----------------------------------------------------------

    public function feedback_save(Request $req)
    {
        Feedback::create([
            'user_id'=>Auth::guard('users')->id(),
            'feedback'=>$req->get('feedback'),
            'created'=>date("Y-m-d H:i:s")
        ]);

        return back()->withSuccess("Thank you for your feedback!");
    }
}
