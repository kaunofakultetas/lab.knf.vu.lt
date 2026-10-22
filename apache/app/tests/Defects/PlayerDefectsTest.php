<?php

// -----------------------------------------------------------
//  [*] PlayerDefectsTest — open defects on the player side
//
//  Each test states the behaviour the lab wants where the app
//  falls short today, under a LAB-nn id; KnownDefects reports
//  it incomplete while the defect is open and fails it once a
//  fix lands. The banner of every test gives the severity,
//  the mechanism with file and line, and the fix.
//
//  Used by:
//    - PHPUnit — the Defects suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Defects;

use Illuminate\View\ViewException;
use Tests\Support\KnownDefects;
use Tests\TestCase;








// -----------------------------------------------------------
// PlayerDefectsTest
// -----------------------------------------------------------
//
// LAB-01 to LAB-05, LAB-20 and LAB-21.
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
    // test_lab_02_a_refused_form_forgets_the_typed_password
    // -----------------------------------------------------------
    //
    // Known bug LAB-02 (low): when the login or registration
    // form is refused, the password typed into it is kept in
    // the session — in Redis, live — until the next request.
    // UserAuthController::login and RegisterController::store
    // call withInput() without arguments, which flashes every
    // field. Fix: flash only the fields the form refills,
    // such as the email and username.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_02_a_refused_form_forgets_the_typed_password(): void
    {
        $this->post('/login', ['email' => 'jonas@players.test', 'password' => 'short']);
        $login = session()->getOldInput('password');
        $this->post('/register', ['username' => 'Jo', 'email' => 'jonas@players.test', 'password' => 'typed-secret-1', 'password_confirmation' => 'typed-secret-1']);
        $registration = session()->getOldInput('password');

        $this->assertDefectStillOpen('LAB-02', function () use ($login, $registration) {
            $this->assertNull($login, 'the login form kept the password');
            $this->assertNull($registration, 'the registration form kept the password');
        });
    }






    // -----------------------------------------------------------
    // test_lab_03_a_malformed_event_range_takes_nothing_down
    // -----------------------------------------------------------
    //
    // Known bug LAB-03 (medium): an event time range saved
    // without the " - " separator — the settings form accepts
    // any text — breaks every player page, the login page
    // included. The sidebar middleware of Users\BaseController
    // (line 64) splits the range and reads the second half
    // unchecked; the error page cannot render either, as its
    // layout reads the variable that middleware never got to
    // share, so the exception escapes Laravel's handler and
    // visitors get an empty server error. Fix: validate the
    // range in Admins\SettingsController::event_data, and
    // fall back gracefully on a malformed stored value.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_03_a_malformed_event_range_takes_nothing_down(): void
    {
        $this->setEventRange('2027-03-01 10:00:00');

        $this->assertDefectStillOpen('LAB-03', function () {
            try {
                $response = $this->get('/');
            } catch (ViewException $crash) {
                $this->fail('even the error page failed: ' . $crash->getMessage());
            }
            $response->assertOk();
        });
    }






    // -----------------------------------------------------------
    // test_lab_04_a_numeric_flag_is_matched_exactly
    // -----------------------------------------------------------
    //
    // Known bug LAB-04 (low): a numeric flag is also accepted
    // in any spelling of the same number — "1e2", "100.0" or
    // "0100" for 100 — because ChallengesController::
    // submit_answer (line 145) compares with ==, which PHP
    // turns into a numeric comparison for numeric strings.
    // Fix: compare with ===.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_04_a_numeric_flag_is_matched_exactly(): void
    {
        $player = $this->makePlayer();
        $challenge = $this->makeChallenge(['answer' => '100']);

        $this->signIn($player)->post("/user/challenge/{$challenge->public_id}", ['answer' => '1e2']);

        $this->assertDefectStillOpen('LAB-04', function () use ($player) {
            $this->assertSame(0, $player->refresh()->points, '"1e2" was accepted for the flag 100');
        });
    }






    // -----------------------------------------------------------
    // test_lab_05_the_api_user_route_answers_anonymous_clients
    // -----------------------------------------------------------
    //
    // Known bug LAB-05 (low): GET /api/user, left over from
    // the Laravel skeleton, answers every request with a
    // server error. Its auth:sanctum guard looks for the
    // "web" guard config/sanctum.php names, which
    // config/auth.php does not define. Fix: delete the route,
    // or point sanctum.guard at the users guard.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_05_the_api_user_route_answers_anonymous_clients(): void
    {
        $response = $this->getJson('/api/user');

        $this->assertDefectStillOpen('LAB-05', function () use ($response) {
            $response->assertUnauthorized();
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
    // turns a short or numeric flag (see LAB-04) into a
    // guessing game. Fix: throttle the answer route per
    // player. Fifty wrong answers in a row are far past any
    // limit a fix is likely to choose.
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
