<?php

// -----------------------------------------------------------
//  [*] ChallengesController — the challenges, as players see
//      them
//
//  The heart of the lab: the dashboard of challenges, a
//  challenge's page, the answer form that books a solve and
//  its points, the thumbs-up and thumbs-down votes, kept in
//  MySQL, and the feedback dialog. Every route asks for a
//  signed-in player.
//
//    GET  /user/dashboard                                — the dashboard
//    GET  /user/challenge/{public_id}/{public_group_id?} — a challenge
//    POST /user/challenge/{public_id}/{public_group_id?} — an answer
//    POST /user/challenge/vote/{type}/{public_id}        — a vote
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
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\Groups;
use App\Models\GroupsChallenges;
use App\Models\GroupsUsers;
use App\Models\User;
use App\Models\Feedback;
use App\Models\Partners;
use App\Models\Categories;
use App\Models\Challenges;
use App\Models\ChallengesHistory;
use App\Models\ChallengesFiles;
use App\Models\ChallengesCategories;
use App\Models\ChallengesSubmitted;
use App\Models\ChallengesVotes;








// -----------------------------------------------------------
// ChallengesController
// -----------------------------------------------------------
//
//   __construct      — the base's layout data
//   index            — the dashboard
//   show             — a challenge's page
//   submit_answer    — an answer, and a solve when it is right
//   vote             — a thumbs-up or thumbs-down
//   dialog_feedback  — the feedback dialog
//   feedback_save    — stores feedback
//   memberOf         — whether the player is in a group
//   hiddenFromPlayer — whether a hidden challenge is closed
//                      to the player
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
    // reads — and its votes. The points filter's boxes start
    // empty, with the cheapest and the dearest challenge on
    // the page as their placeholders: the filter (custom.js)
    // reads an empty box as no bound, so no card is filtered
    // out before the player types a bound, and the cookies
    // that keep the filter keep only what the player typed,
    // never the prices of the moment. The sorting helper is
    // declared inside the action, so the action runs once per
    // PHP process: Apache serves each request afresh, and the
    // tests render these pages in separate processes.
    //
    // Used by:
    //   - the Home link of the player sidebar
    //     (users/partials/sidebar.blade.php), the end of every
    //     login, and the redirects of the challenge pages
    //   - the guest middleware, which sends a signed-in player
    //     here from the login, registration and password pages
    // -----------------------------------------------------------

    public function index(Request $request)
    {
        // The visible challenges, with the player's solves, the
        // categories and the votes
        if (count(Challenges::where(['hidden'=>0])->orderby('points')->get()->toArray()) > 0) {
            $arr = Challenges::where(['hidden'=>0])->orderby('points')->get()->toArray();

            $tally = ChallengesVotes::tally(array_column($arr, 'id'));
            $voted = ChallengesVotes::where(['user_id' => Auth::guard('users')->id()])->pluck('vote', 'challenge_id');

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

                $arr[$k]['thumbs_up_count'] = $tally[$v['id']]['up'] ?? 0;
                $arr[$k]['thumbs_down_count'] = $tally[$v['id']]['down'] ?? 0;
                $arr[$k]['voted'] = $voted[$v['id']] ?? 0;
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

        // The bounds the points filter starts from
        $points = array_column($arr, 'points');

        return view('users.dashboard', [
            'recordlist' => $arr,
            'categories' => Categories::all()->pluck('name', 'id')->toArray(),
            'min_points' => $points ? min($points) : '',
            'max_points' => $points ? max($points) : ''
        ]);
    }






    // -----------------------------------------------------------
    // show
    // -----------------------------------------------------------
    //
    // GET /user/challenge/{public_id}/{public_group_id?}
    //
    // The users.challenge view: the challenge — its content
    // printed as HTML, a lab admin's cleaned of scripts when
    // it was saved — its partner, file links and votes. An
    // unknown challenge goes back to the dashboard with
    // "Challenge doesn't exists!", an unknown group back with
    // "Error!". A hidden challenge — one kept for groups —
    // answers as an unknown one, whatever group the address
    // names, unless the player is a member of a group it is
    // placed in (hiddenFromPlayer). The validate.ifsolved
    // middleware has sent solved ones to the dashboard
    // already. A challenge whose partner was deleted shows
    // without the partner card.
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

        if ($this->hiddenFromPlayer($public_id)) {
            return redirect(route('user_dashboard'))->withErrors(['Challenge doesn\'t exists!']);
        }

        $challenge_id = Challenges::getIdByPublicId($public_id);
        $files = ChallengesFiles::getFilesById($challenge_id);
        $tally = ChallengesVotes::tally([$challenge_id])[$challenge_id] ?? ['up' => 0, 'down' => 0];

        return view('users.challenge', [
            'data'=>Challenges::where(['public_id'=>$public_id])->get()->first(),
            'partner'=>Partners::where(['id'=>Challenges::getPartnerIdByPublicId($public_id)])->get()->first(),
            'files'=>$files,
            'public_id' => $public_id,
            'public_group_id' => $public_group_id,
            'thumbs_up_count'=>$tally['up'],
            'thumbs_down_count'=>$tally['down'],
            'voted'=>ChallengesVotes::where(['user_id' => Auth::guard('users')->id(), 'challenge_id' => $challenge_id])->value('vote') ?? 0
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
    // and a row of the solve feed; then back to the dashboard
    // — or the group's page — with the points won.
    // Every "VU{" and "}" is removed from the answer and from
    // the stored flag alike before the compare, so a flag
    // matches with or without its wrapper, however it was
    // saved; past that the compare is exact, so a numeric flag
    // matches only as written, and an empty answer, or one
    // that is not text, never matches. Wrong answers and
    // solved challenges go back with "Wrong answer" or
    // "Already solved". The points are added in SQL, so solves
    // two tabs book at the same moment all count; the same
    // right answer sent twice at once — a double click —
    // passes both "already solved" checks, and the second
    // solve, which the table's unique key refuses, goes back
    // as solved too. The booking holds a shared lock on the
    // challenge's row, so a delete of the challenge either
    // waits for the solve and takes its points back, or runs
    // first, and the answer then goes to the dashboard as a
    // challenge that does not exist. A hidden challenge takes
    // no answer from a player it is hidden from, as in show.
    //
    // A right answer from a group's page goes back to that
    // group's page, but to the dashboard when the player is
    // not in the group (memberOf) — a link a member shared, or
    // a membership removed while the page was open — as the
    // group's page would answer 404.
    //
    // By design answers have no rate limit.
    //
    // Used by:
    //   - the answer form of users/challenge.blade.php
    // -----------------------------------------------------------

    public function submit_answer(Request $request, $public_id, $public_group_id=NULL)
    {
        // STEP 1: the challenge and the group must exist, and
        //         the challenge must be open to the player
        // ===================================================
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

        if ($this->hiddenFromPlayer($public_id)) {
            return redirect(route('user_dashboard'))->withErrors(['Challenge doesn\'t exists!']);
        }


        // STEP 2: the answer and the challenge's flag, both
        //         without the wrapper, and the challenge's points
        // =======================================================
        // An answer that is not text — only a hand-made request
        // sends one — counts as no answer
        $answer = $request->input('answer');
        $answer = is_string($answer) ? trim(str_replace("VU{","",str_replace("}","",$answer))) : '';

        $challenge_id = Challenges::getIdByPublicId($public_id);

        $challenge_answer = Challenges::where(['public_id' => $public_id])->get()->pluck('answer')->first();
        $challenge_answer = trim(str_replace("VU{","",str_replace("}","",$challenge_answer)));

        $challenge_points = Challenges::where(['public_id' => $public_id])->get()->pluck('points')->first();

        $solved = ChallengesSubmitted::where('user_id', '=', Auth::guard('users')->id())->where('challenge_id', '=', $challenge_id)->count();


        // STEP 3: a right answer books the solve, the points and
        // the feed row
        // ======================================================
        if ($solved == 0) {
            if ($answer !== '' && $challenge_answer === $answer) {
                $booked = DB::transaction(function () use ($challenge_id, $challenge_points) {
                    // The challenge's row, share-locked until the
                    // solve is booked: a delete of the challenge
                    // waits for it, or ran first and left nothing
                    $challenge = Challenges::whereKey($challenge_id)->sharedLock()->first();

                    if ($challenge === null) {
                        return 'gone';
                    }

                    $submitTask = new ChallengesSubmitted();
                    $submitTask->user_id = Auth::guard('users')->id();
                    $submitTask->challenge_id = $challenge_id;
                    $submitTask->points_given = $challenge_points;
                    $submitTask->created = date('Y-m-d H:i:s');
                    $submitTask->ip = SettingsController::getUserIP();

                    // MySQL's duplicate-entry error: the same solve booked
                    // a moment ago by the other half of a double click
                    try {
                        $submitTask->save();
                    } catch (QueryException $e) {
                        if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
                            throw $e;
                        }

                        return 'solved';
                    }

                    // A missing total counts as 0, as it always has
                    User::where(["id" => Auth::guard('users')->id()])->update(
                        ["points" => DB::raw('IFNULL(points, 0) + ' . (int) $challenge_points)]);

                    // The content line is neither fillable nor a column,
                    // so the model drops it
                    ChallengesHistory::create([
                        'user_id'=>Auth::guard('users')->id(),
                        'challenge_id'=>$challenge_id,
                        'content' => User::find(Auth::guard('users')->id())->getUsername() . ' solved ' . $challenge->name . ' challenge',
                        'created_time' => time(),
                        'created' => date('Y-m-d H:i:s')
                    ]);

                    return 'booked';
                });

                if ($booked === 'gone') {
                    return redirect(route('user_dashboard'))->withErrors(['Challenge doesn\'t exists!']);
                }

                if ($booked === 'solved') {
                    return back()->withErrors(['Already solved']);
                }

                if($public_group_id == NULL || ! $this->memberOf($public_group_id)){
                    return redirect(route('user_dashboard'))->withSuccess("Your answer is correct! You received $challenge_points points");
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
    // POST /user/challenge/vote/{type}/{public_id}
    //
    // One vote per player and challenge, up or down, kept as a
    // row of cyber_challenges_votes (ChallengesVotes): the
    // table's unique key leaves out a second vote of the same
    // player, even two sent at once, and the pages count the
    // rows, so the votes last as long as the database — Redis,
    // which a stack update starts empty, holds none. Any other
    // type, a challenge that does not exist and a hidden one
    // the player may not open (hiddenFromPlayer) are a 404 and
    // count nothing. The answer is an empty page.
    //
    // Used by:
    //   - thumbs() and thumbs_list() of custom.js — the vote
    //     buttons of the dashboard and the challenge page
    // -----------------------------------------------------------

    public function vote(Request $req, $type, $public_id)
    {
        if (! in_array($type, ['up', 'down'], true) || Challenges::where(['public_id' => $public_id])->doesntExist() || $this->hiddenFromPlayer($public_id)) {
            abort(404);
        }

        ChallengesVotes::insertOrIgnore([
            'user_id' => Auth::guard('users')->id(),
            'challenge_id' => Challenges::getIdByPublicId($public_id),
            'vote' => $type,
            'created' => date('Y-m-d H:i:s'),
        ]);
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
    // "Thank you for your feedback!". The text may be empty;
    // a field that is not text — only a hand-made request
    // sends one — goes back with the error and stores
    // nothing. The text is read as the rule reads it, the
    // form's body before the address, so the text stored is
    // always the one checked.
    //
    // Used by:
    //   - the form of the feedback dialog
    // -----------------------------------------------------------

    public function feedback_save(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'feedback' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        Feedback::create([
            'user_id'=>Auth::guard('users')->id(),
            'feedback'=>$req->input('feedback'),
            'created'=>date("Y-m-d H:i:s")
        ]);

        return back()->withSuccess("Thank you for your feedback!");
    }






    // -----------------------------------------------------------
    // memberOf
    // -----------------------------------------------------------
    //
    // Whether the signed-in player belongs to the group with
    // the public id, by VU id, as GroupExists asks before a
    // group's page opens; a local account belongs to no group.
    //
    // Used by:
    //   - submit_answer (above) — where a right answer goes on
    //     to
    // -----------------------------------------------------------

    private function memberOf($public_group_id)
    {
        $vu_id = Auth::guard('users')->user()->vu_id;

        if (empty($vu_id)) {
            return false;
        }

        return GroupsUsers::where(['vu_id' => $vu_id])
            ->whereIn('group_id', Groups::where(['public_id' => $public_group_id])->pluck('id'))
            ->exists();
    }






    // -----------------------------------------------------------
    // hiddenFromPlayer
    // -----------------------------------------------------------
    //
    // Whether the challenge is closed to the signed-in player:
    // a hidden challenge — one a lab admin keeps for groups —
    // is open only to the members of a group it is placed in,
    // the membership the group pages check (GroupExists).
    // Members are kept by VU id, so a local account is never
    // one. A challenge that is not hidden is open to every
    // player.
    //
    // Used by:
    //   - show, submit_answer and vote (above)
    // -----------------------------------------------------------

    private function hiddenFromPlayer($public_id)
    {
        $challenge = Challenges::where(['public_id' => $public_id])->first();

        if ($challenge->hidden != 1) {
            return false;
        }

        $vu_id = Auth::guard('users')->user()->vu_id;

        if (empty($vu_id)) {
            return true;
        }

        return ! GroupsChallenges::where(['challenge_id' => $challenge->id])
            ->whereIn('group_id', GroupsUsers::where(['vu_id' => $vu_id])->pluck('group_id'))
            ->exists();
    }
}
