<?php

// -----------------------------------------------------------
//  [*] RaceDefectsTest — open defects under simultaneous requests
//
//  Two requests that land at the same moment — a player's two
//  tabs, two players, a double click — can step on each other
//  wherever the app reads a value, works on it in PHP and
//  writes the result back. A test cannot send two requests at
//  once, so each test here plays the other request itself: it
//  listens to its own request's database queries or Redis
//  commands and slips the other request's write in exactly
//  where it lands in production. Each test states the
//  behaviour the lab wants under a LAB-nn id; KnownDefects
//  reports it incomplete while the defect is open and fails it
//  once a fix lands.
//
//  Used by:
//    - PHPUnit — the Defects suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Defects;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Redis\Events\CommandExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Tests\Support\KnownDefects;
use Tests\TestCase;








// -----------------------------------------------------------
// RaceDefectsTest
// -----------------------------------------------------------
//
// LAB-22 to LAB-24. A test whose injection point disappears
// with a fix — a write that no longer reads first has no gap
// to slip into — expects the outcome without the other
// request, so it reports the defect fixed.
//
// Used by:
//   - PHPUnit — the Defects suite (runTests.sh)
// -----------------------------------------------------------

final class RaceDefectsTest extends TestCase
{

    use KnownDefects;






    // -----------------------------------------------------------
    // test_lab_22_simultaneous_solves_keep_all_points
    // -----------------------------------------------------------
    //
    // Known bug LAB-22 (low): two answers a player gets right
    // at the same moment — two tabs, two challenges — can
    // lose one challenge's points. submit_answer
    // (Users\ChallengesController, line 340) reads the
    // player's points, adds in PHP and writes the sum back, so
    // a solve booked between the read and the write is
    // overwritten. The other tab's 25-point solve is booked
    // right after this request first reads the account once
    // its own submission row is in. Fix: add the points in
    // SQL, with increment().
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_22_simultaneous_solves_keep_all_points(): void
    {
        $player = $this->makePlayer();
        $challenge = $this->makeChallenge(['points' => 10]);
        $booked = false;
        $interleaved = false;
        DB::listen(function (QueryExecuted $query) use (&$booked, &$interleaved, $player) {
            if (str_starts_with($query->sql, 'insert into `cyber_challenges_submitted`')) {
                $booked = true;
            } elseif ($booked && !$interleaved && preg_match('/^select .* from `cyber_users`/', $query->sql)) {
                $interleaved = true;
                DB::table('cyber_users')->where('id', $player->id)->increment('points', 25);
            }
        });

        $this->signIn($player)->post("/user/challenge/{$challenge->public_id}", ['answer' => $challenge->answer]);

        $this->assertDefectStillOpen('LAB-22', function () use ($player, $interleaved) {
            $this->assertSame(10 + ($interleaved ? 25 : 0), $player->refresh()->points, "the other tab's 25 points were lost");
        });
    }






    // -----------------------------------------------------------
    // test_lab_23_simultaneous_votes_are_all_counted
    // -----------------------------------------------------------
    //
    // Known bug LAB-23 (low): two votes on a challenge at the
    // same moment can count as one. vote()
    // (Users\ChallengesController, line 410) reads the counter
    // from Redis, adds one in PHP and writes it back; another
    // player's vote lands between the read and the write.
    // Fix: Redis INCR.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_23_simultaneous_votes_are_all_counted(): void
    {
        $challenge = $this->makeChallenge();
        $counter = "ThumbsUp_{$challenge->public_id}";
        $interleaved = false;
        Redis::enableEvents();
        Redis::purge();
        Event::listen(CommandExecuted::class, function (CommandExecuted $event) use ($counter, &$interleaved) {
            if (!$interleaved && strtolower($event->command) === 'get' && ($event->parameters[0] ?? null) === $counter) {
                $interleaved = true;
                Redis::incr($counter);
            }
        });

        $this->signIn($this->makePlayer())->get("/user/challenge/vote/up/{$challenge->public_id}");

        $this->assertDefectStillOpen('LAB-23', function () use ($counter, $interleaved) {
            $this->assertSame(1 + ($interleaved ? 1 : 0), (int) Redis::get($counter), "the other player's vote was lost");
        });
    }






    // -----------------------------------------------------------
    // test_lab_24_a_double_submitted_answer_is_refused_cleanly
    // -----------------------------------------------------------
    //
    // Known bug LAB-24 (low): the same right answer sent twice
    // at once — a double click — ends the second request on
    // the server error page. Both requests pass the "already
    // solved" checks before either has booked the solve; the
    // unique key of the race-condition fix then refuses the
    // second insert, and its QueryException surfaces as a
    // 500. The points stay right. The first request's solve
    // is booked right after this request's last check. Fix:
    // catch the duplicate and answer as for a solved
    // challenge.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_24_a_double_submitted_answer_is_refused_cleanly(): void
    {
        $player = $this->makePlayer();
        $challenge = $this->makeChallenge();
        $checks = 0;
        DB::listen(function (QueryExecuted $query) use (&$checks, $player, $challenge) {
            if (str_starts_with($query->sql, 'select count(*) as aggregate from `cyber_challenges_submitted`') && ++$checks === 2) {
                DB::table('cyber_challenges_submitted')->insert([
                    'user_id'      => $player->id,
                    'challenge_id' => $challenge->id,
                    'points_given' => $challenge->points,
                    'created'      => date('Y-m-d H:i:s'),
                    'ip'           => '127.0.0.1',
                ]);
            }
        });

        $response = $this->signIn($player)->post("/user/challenge/{$challenge->public_id}", ['answer' => $challenge->answer]);

        $this->assertDefectStillOpen('LAB-24', function () use ($response) {
            $this->assertNotSame(500, $response->getStatusCode(), 'the second answer ended on the server error page');
            $response->assertRedirect();
        });
    }
}
