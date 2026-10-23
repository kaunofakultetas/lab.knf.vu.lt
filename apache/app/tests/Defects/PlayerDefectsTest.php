<?php

// -----------------------------------------------------------
//  [*] PlayerDefectsTest — open defects on the player side
//
//  Each test states the behaviour the lab wants where the app
//  falls short today, under a LAB-nn id; KnownDefects reports
//  it incomplete while the defect is open and fails it once a
//  fix lands. The banner of every test gives the severity,
//  the mechanism and where it lives, and the fix.
//
//  Used by:
//    - PHPUnit — the Defects suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Defects;

use Tests\Support\KnownDefects;
use Tests\TestCase;








// -----------------------------------------------------------
// PlayerDefectsTest
// -----------------------------------------------------------
//
// LAB-01, LAB-20 and LAB-21.
//
// Used by:
//   - PHPUnit — the Defects suite (runTests.sh)
// -----------------------------------------------------------

final class PlayerDefectsTest extends TestCase
{

    use KnownDefects;






    // -----------------------------------------------------------
    // test_lab_01_the_leaderboard_shows_the_latest_solve
    // -----------------------------------------------------------
    //
    // Known bug LAB-01 (low): "Last challenge solved" on the
    // leaderboards is not the latest solve but whichever of
    // the player's solves MySQL reads first. The
    // cyber_ranking_table view (mysql/init.sql) groups the
    // submissions per user and selects their created column
    // without aggregating it; the plan in use reads them in
    // index order, so it is usually the solve of the oldest
    // challenge. Fix: select
    // MAX(cyber_challenges_submitted.created) in the view.
    //
    // The latest of the three solves sits in the middle both
    // by row id and by challenge id, so no plan that keeps
    // the first or the last row it reads can show it by
    // chance.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_01_the_leaderboard_shows_the_latest_solve(): void
    {
        $player = $this->makePlayer();
        $this->recordSolve($player, $this->makeChallenge(), date('Y-m-d H:i:s', strtotime('-5 days')));
        $this->recordSolve($player, $this->makeChallenge(), date('Y-m-d H:i:s', strtotime('-2 hours')));
        $this->recordSolve($player, $this->makeChallenge(), date('Y-m-d H:i:s', strtotime('-10 days')));

        $response = $this->signIn($this->makePlayer())->get('/user/leaderboard');

        $this->assertDefectStillOpen('LAB-01', function () use ($response) {
            $response->assertSee('2 hours ago');
        });
    }






    // -----------------------------------------------------------
    // test_lab_20_password_guessing_is_throttled
    // -----------------------------------------------------------
    //
    // Known bug LAB-20 (medium): the login form takes any
    // number of wrong passwords — POST /login has no rate
    // limit, no lockout and no reCAPTCHA — so a password can
    // be guessed without end. Fix: throttle the login route,
    // by address and email. Thirty failures in a row are far
    // past any limit a fix is likely to choose.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_20_password_guessing_is_throttled(): void
    {
        $player = $this->makePlayer();
        foreach (range(1, 30) as $attempt) {
            $this->post('/login', ['email' => $player->email, 'password' => "wrong-guess-{$attempt}"]);
        }

        $this->post('/login', ['email' => $player->email, 'password' => self::PASSWORD]);

        $this->assertDefectStillOpen('LAB-20', function () {
            $this->assertGuest('users');
        });
    }






    // -----------------------------------------------------------
    // test_lab_21_flag_guessing_is_throttled
    // -----------------------------------------------------------
    //
    // Known bug LAB-21 (low): answers have no rate limit
    // either, so a player can try flags without end, which
    // turns a short or numeric flag into a guessing game.
    // Fix: throttle the answer route per player. Fifty wrong
    // answers in a row are far past any limit a fix is likely
    // to choose.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_21_flag_guessing_is_throttled(): void
    {
        $player = $this->makePlayer();
        $challenge = $this->makeChallenge(['answer' => '4821']);
        $this->signIn($player);
        foreach (range(1, 50) as $guess) {
            $this->post("/user/challenge/{$challenge->public_id}", ['answer' => (string) $guess]);
        }

        $this->post("/user/challenge/{$challenge->public_id}", ['answer' => '4821']);

        $this->assertDefectStillOpen('LAB-21', function () use ($player) {
            $this->assertSame(0, $player->refresh()->points, 'the 51st answer in a row was still checked');
        });
    }
}
