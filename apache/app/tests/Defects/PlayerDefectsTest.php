<?php

// -----------------------------------------------------------
//  [*] PlayerDefectsTest — open defects of the player side
//
//  Each test states the behaviour the lab wants where the
//  player side falls short today, under a LAB-nn id;
//  KnownDefects reports it incomplete while the defect is
//  open and fails it once a fix lands. The banner of every
//  test gives the severity, the mechanism, naming its files
//  and methods, and the fix. The dashboard's own defect is
//  DashboardDefectsTest's, as that page may only be
//  rendered once per PHP process.
//
//  Used by:
//    - PHPUnit — the Defects suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Defects;

use Illuminate\Support\Facades\Redis;
use Tests\Support\KnownDefects;
use Tests\TestCase;








// -----------------------------------------------------------
// PlayerDefectsTest
// -----------------------------------------------------------
//
// LAB-55 and LAB-60.
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
    // test_lab_55_votes_outlive_an_emptied_redis
    // -----------------------------------------------------------
    //
    // Known bug LAB-55 (medium): the votes live in Redis alone
    // — the ThumbsUp_ and ThumbsDown_ counters and the
    // player's Vote_ mark (Users\ChallengesController::vote,
    // index and show) — and Redis is the one store the stack
    // does not keep: vuknflab-redis has no volume in
    // docker-compose.yml, and runUpdateThisStack.sh takes the
    // stack down and up, so every update starts it empty.
    // Every count goes back to 0 and every player may vote
    // again. Here a vote is cast and Redis emptied, as an
    // update leaves it. Fix: keep the votes in MySQL — a row
    // per player and challenge, whose unique key also keeps
    // the one vote — and count them from there, as the solve
    // feed moved out of Redis for the same reason; the live
    // Redis votes need copying over once.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_55_votes_outlive_an_emptied_redis(): void
    {
        $challenge = $this->makeChallenge();
        $this->signIn($this->makePlayer())->post("/user/challenge/vote/up/{$challenge->public_id}");
        Redis::connection()->flushdb();

        $response = $this->get("/user/challenge/{$challenge->public_id}");

        $this->assertDefectStillOpen('LAB-55', function () use ($response) {
            $response->assertSee('id="thumbs-up-count">1<', false);
            $response->assertSee('disabled class="badge border-0 text-white bg-primary thumbs-up"', false);
        });
    }






    // -----------------------------------------------------------
    // test_lab_60_a_right_answer_never_ends_on_a_group_page_the_player_cannot_open
    // -----------------------------------------------------------
    //
    // Known bug LAB-60 (low): Users\ChallengesController's
    // show and submit_answer check only that the group in the
    // address exists, never that the player belongs to it, and
    // a right answer then goes on to that group's page, which
    // GroupExists answers with a 404 for anyone outside the
    // group. A member who shares a group challenge's link, or
    // a student removed from the group while the page was
    // open, scores and lands on "Error 404"; the success toast
    // is lost too, as the error layout's script stops at
    // selectpicker, which no bundle defines. Fix: treat a
    // group the player is not in as no group — back to the
    // dashboard after the answer.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_60_a_right_answer_never_ends_on_a_group_page_the_player_cannot_open(): void
    {
        $challenge = $this->makeChallenge(['points' => 15]);
        $group = $this->makeGroup();
        $this->assignToGroup($group, $challenge);
        $page = "/user/challenge/{$challenge->public_id}/{$group->public_id}";

        $response = $this->signIn($this->makePlayer())->from($page)->post($page, ['answer' => $challenge->answer]);

        $response->assertSessionHas('success', 'Your answer is correct! You received 15 points');
        $this->assertDefectStillOpen('LAB-60', function () use ($response, $group) {
            $this->assertNotSame(url("/user/group/{$group->public_id}"), $response->headers->get('Location'), 'the answer goes on to a group page that answers 404');
        });
    }
}
