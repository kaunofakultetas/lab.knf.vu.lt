<?php

// -----------------------------------------------------------
//  [*] PlayerDefectsTest — open defects of the player side
//
//  Each test states the behaviour the lab wants where the
//  player side falls short today, under a LAB-nn id;
//  KnownDefects reports it incomplete while the defect is
//  open and fails it once a fix lands. The banner of every
//  test gives the severity, the mechanism, naming its files
//  and methods, and the fix.
//
//  Used by:
//    - PHPUnit — the Defects suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Defects;

use App\Models\Feedback;
use App\Models\Groups;
use Tests\Support\KnownDefects;
use Tests\TestCase;








// -----------------------------------------------------------
// PlayerDefectsTest
// -----------------------------------------------------------
//
// LAB-75, LAB-77 and LAB-84.
//
// Used by:
//   - PHPUnit — the Defects suite (runTests.sh)
// -----------------------------------------------------------

final class PlayerDefectsTest extends TestCase
{

    // assertDefectStillOpen, which reports a test incomplete
    // while its defect is open
    use KnownDefects;






    // -----------------------------------------------------------
    // test_lab_75_feedback_has_a_length_limit
    // -----------------------------------------------------------
    //
    // Known bug LAB-75 (low): feedback is checked only as text
    // (Users\ChallengesController::feedback_save) and stored in
    // a LONGTEXT column, so a player may store up to PHP's
    // 8 MB a message, as often as they like, and fill the
    // database's disk. Here a megabyte of feedback is sent.
    // Fix: a length limit of a few thousand characters.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_75_feedback_has_a_length_limit(): void
    {
        $player = $this->makePlayer();

        $this->signIn($player)->from('/user/dashboard')->post('/user/feedback', ['feedback' => str_repeat('Long feedback. ', 70000)]);

        $this->assertDefectStillOpen('LAB-75', function () use ($player) {
            $this->assertSame(0, Feedback::where('user_id', $player->id)->count(), 'a megabyte of feedback is stored');
        });
    }






    // -----------------------------------------------------------
    // test_lab_77_a_challenge_page_whose_group_is_gone_never_redirects_to_itself
    // -----------------------------------------------------------
    //
    // Known bug LAB-77 (low): a challenge page opened through a
    // group that no longer exists goes back()
    // (Users\ChallengesController::show, and submit_answer the
    // same), and back() is the address the browser came from:
    // after an answer sent from that very page, the page
    // itself, as the browser keeps the referrer across
    // redirects. The page then redirects to itself until the
    // browser gives up with "too many redirects" — what a
    // player whose group was deleted while a challenge page was
    // open meets with the next answer or reload. The control
    // panel had the same loop, fixed as LAB-58. Here the page
    // is opened from itself after its group is deleted.
    // ChallengeTest::test_a_group_page_needs_an_existing_group
    // holds today's back() and changes with the fix. Fix: send
    // an unknown group to the dashboard, as an unknown
    // challenge is.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_77_a_challenge_page_whose_group_is_gone_never_redirects_to_itself(): void
    {
        $challenge = $this->makeChallenge();
        $group = $this->makeGroup();
        $this->assignToGroup($group, $challenge);
        $player = $this->makeSsoPlayer();
        $this->joinGroup($group, $player);
        $page = "/user/challenge/{$challenge->public_id}/{$group->public_id}";
        Groups::whereKey($group->id)->delete();

        $response = $this->signIn($player)->from($page)->get($page);

        $this->assertDefectStillOpen('LAB-77', function () use ($response, $page) {
            $this->assertNotSame(url($page), $response->headers->get('Location'), 'the page redirects to itself');
        });
    }






    // -----------------------------------------------------------
    // test_lab_84_a_solve_worth_no_points_never_loses_a_tie
    // -----------------------------------------------------------
    //
    // Known bug LAB-84 (low): players with the same points are
    // ranked by who got there first, read as their latest
    // solve — the highest solve id, in
    // Users\SettingsController::update_rank_table and in the
    // get_group_leaderboard of Users\GroupsController and of
    // Admins\UsersGroupsController — and a solve worth no
    // points counts as one, so a player who solves a 0-point
    // challenge, a rules or welcome task, after reaching their
    // total drops behind everyone who reached it in between,
    // on the leaderboard and on the group leaderboards alike.
    // Here EarlyBird reaches 10 points before LateComer and
    // then solves a 0-point challenge. Fix: take the latest
    // solve that gave points.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_84_a_solve_worth_no_points_never_loses_a_tie(): void
    {
        $early = $this->makeSsoPlayer(['username' => 'EarlyBird', 'display_name' => 'EarlyBird']);
        $late = $this->makeSsoPlayer(['username' => 'LateComer', 'display_name' => 'LateComer']);
        $ten = $this->makeChallenge(['points' => 10]);
        $rules = $this->makeChallenge(['points' => 0]);
        $group = $this->makeGroup();
        $this->assignToGroup($group, $ten);
        $this->assignToGroup($group, $rules);
        $this->joinGroup($group, $early);
        $this->joinGroup($group, $late);
        $this->recordSolve($early, $ten);
        $this->recordSolve($late, $ten);
        $this->recordSolve($early, $rules);

        $pages = [
            'the leaderboard' => $this->pageContent($this->signIn($early)->get('/user/leaderboard')),
            'the group leaderboard' => $this->pageContent($this->get("/user/group/leaderboard/{$group->public_id}")),
            "the panel's group leaderboard" => $this->signInAdmin($this->makeAdmin(true))->get("/control-panel/users/vu-groups/leaderboard/{$group->id}")->getContent(),
        ];

        $this->assertDefectStillOpen('LAB-84', function () use ($pages) {
            foreach ($pages as $name => $html) {
                $this->assertMatchesRegularExpression('/EarlyBird.*LateComer/s', $html, "{$name} ranks LateComer first");
            }
        });
    }
}
