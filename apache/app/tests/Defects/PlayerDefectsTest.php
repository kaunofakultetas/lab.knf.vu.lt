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
//  DashboardDefectsTest's, as that page may only be rendered
//  once per PHP process.
//
//  Used by:
//    - PHPUnit — the Defects suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Defects;

use App\Models\Challenges;
use App\Models\ChallengesHistory;
use App\Models\Feedback;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\Support\KnownDefects;
use Tests\TestCase;








// -----------------------------------------------------------
// PlayerDefectsTest
// -----------------------------------------------------------
//
// LAB-44, LAB-46, LAB-47 and LAB-53.
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
    // test_lab_44_a_form_saves_the_values_it_checked
    // -----------------------------------------------------------
    //
    // Known bug LAB-44 (low): the forms validate the request
    // body ($request->all() reads the body before the address)
    // but save $request->get(), which Symfony reads from the
    // address before the body. A value put into the form's
    // address skips the rules: feedback sent there as a list
    // ends in a server error
    // (Users\ChallengesController::feedback_save), and a
    // challenge name there is stored however long, cut to the
    // column's 255 characters
    // (Admins\ChallengesController::store); the account, page,
    // category, mail and group forms read the same way. Only a
    // hand-made request does this. Fix: save what the
    // validator checked, as the file links do since their fix.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_44_a_form_saves_the_values_it_checked(): void
    {
        $player = $this->makePlayer();
        $feedback = $this->signIn($player)
            ->from('/user/leaderboard')
            ->post('/user/feedback?feedback[]=x', ['feedback' => 'Great lab']);

        $this->signInAdmin($this->makeAdmin(true))->from('/control-panel/challenges/create');
        $this->post('/control-panel/challenges/store?name=' . str_repeat('n', 300), [
            'name'       => 'Packet Detective',
            'partner_id' => $this->makePartner()->id,
            'descr'      => 'Find the flag in the capture.',
            'content'    => '<p>Look closer.</p>',
            'answer'     => 'packet_wizard',
            'points'     => 30,
        ]);

        $this->assertDefectStillOpen('LAB-44', function () use ($feedback, $player) {
            $feedback->assertRedirect('/user/leaderboard');
            $this->assertSame('Great lab', Feedback::where('user_id', $player->id)->value('feedback'));
            $this->assertTrue(Challenges::where('name', 'Packet Detective')->exists());
        });
    }






    // -----------------------------------------------------------
    // test_lab_46_the_solve_feed_survives_an_empty_redis
    // -----------------------------------------------------------
    //
    // Known bug LAB-46 (low): tasks_history, the sidebar's
    // feed of recent solves, is written only when a solve or
    // an admin's change rebuilds it
    // (ChallengesHistory::refreshFeed), and the sidebar
    // (Users\BaseController) reads a missing key as an empty
    // feed. Redis keeps nothing across a stack update, as
    // runUpdateThisStack.sh takes the stack down and up, so
    // afterwards every player's History stays empty until the
    // next right answer, although the solves are in the
    // database. Fix: rebuild the feed when the key is missing.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_46_the_solve_feed_survives_an_empty_redis(): void
    {
        $this->recordSolve($this->makePlayer(['username' => 'FastSolver']), $this->makeChallenge(['name' => 'Packet Detective']));

        $response = $this->signIn($this->makePlayer())->get('/user/leaderboard');

        $this->assertDefectStillOpen('LAB-46', function () use ($response) {
            $response->assertSee('<strong>FastSolver</strong> solved Packet Detective challenge', false);
        });
    }






    // -----------------------------------------------------------
    // test_lab_47_two_solves_at_once_both_reach_the_feed
    // -----------------------------------------------------------
    //
    // Known bug LAB-47 (low): ChallengesHistory::refreshFeed
    // reads the six latest solves and then overwrites
    // tasks_history with them, with no ordering between
    // rebuilds. When two right answers land together, the
    // rebuild that read first can write last, and the feed
    // then misses the other solve until the next one. Here the
    // second solve and its rebuild run in full between the
    // first rebuild's read and its write. Fix: build the feed
    // from the database when it is read, or write it only from
    // a read newer than the one stored.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_47_two_solves_at_once_both_reach_the_feed(): void
    {
        $first = $this->makeChallenge(['name' => 'First Task']);
        $second = $this->makeChallenge(['name' => 'Second Task']);
        $other = $this->makePlayer(['username' => 'OtherSolver']);
        $interleaved = false;
        DB::listen(function (QueryExecuted $query) use ($second, $other, &$interleaved) {
            if (!$interleaved && str_contains($query->sql, 'from `cyber_challenges_history` inner join')) {
                $interleaved = true;
                $this->recordSolve($other, $second);
                ChallengesHistory::refreshFeed();
            }
        });

        $this->signIn($this->makePlayer())->post("/user/challenge/{$first->public_id}", ['answer' => $first->answer]);

        $this->assertTrue($interleaved);
        $this->assertDefectStillOpen('LAB-47', function () {
            $this->assertStringContainsString('Second Task', (string) Redis::get('tasks_history'));
        });
    }






    // -----------------------------------------------------------
    // test_lab_53_the_player_pages_let_the_browser_keep_the_script
    // -----------------------------------------------------------
    //
    // Known bug LAB-53 (low): the player layout
    // (users/layouts/app.blade.php) links its script bundle
    // with the current time in the address, a new address
    // every second, so the browser fetches the 456 KB
    // app.min.js again on every player page. The control
    // panel's layout links its bundle with the file's time
    // instead, which changes only when the file does. Fix: use
    // filemtime here too.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_53_the_player_pages_let_the_browser_keep_the_script(): void
    {
        $response = $this->signIn($this->makePlayer())->get('/user/leaderboard');

        $this->assertDefectStillOpen('LAB-53', function () use ($response) {
            $response->assertSee('/assets/js/app.min.js?' . filemtime(public_path('assets/js/app.min.js')), false);
        });
    }
}
