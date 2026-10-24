<?php

// -----------------------------------------------------------
//  [*] AdminDefectsTest — open defects of the control panel
//
//  Each test states the behaviour the lab wants where the
//  control panel falls short today, under a LAB-nn id;
//  KnownDefects reports it incomplete while the defect is
//  open and fails it once a fix lands. The banner of every
//  test gives the severity, the mechanism, naming its files
//  and methods, and the fix. A race is played out in one
//  request: a query hook (DB::listen) runs the other
//  admin's action at the moment it would slip in.
//
//  Used by:
//    - PHPUnit — the Defects suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Defects;

use App\Http\Controllers\Admins\ChallengesController;
use App\Models\Challenges;
use App\Models\GroupsChallenges;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Tests\Support\KnownDefects;
use Tests\TestCase;








// -----------------------------------------------------------
// AdminDefectsTest
// -----------------------------------------------------------
//
// LAB-81, LAB-82 and LAB-83.
//
// Used by:
//   - PHPUnit — the Defects suite (runTests.sh)
// -----------------------------------------------------------

final class AdminDefectsTest extends TestCase
{

    // assertDefectStillOpen, which reports a test incomplete
    // while its defect is open
    use KnownDefects;






    // -----------------------------------------------------------
    // test_lab_81_a_refused_main_only_page_never_redirects_to_itself
    // -----------------------------------------------------------
    //
    // Known bug LAB-81 (low): Perms::handle answers a refused
    // main-only request with back(), which, for a page the
    // browser reached from a main-only page — the same page,
    // after any action on it — is that page again, refused
    // again: a main admin demoted while a main-only page is
    // open, who then presses anything there, is redirected to
    // the page until the browser gives up, and never sees the
    // error; the panel's dialogs loop the same way inside
    // their request. R_Exists had the same loop, fixed as
    // LAB-58. Here a lab admin asks for the settings page from
    // the settings page. Fix: send a refused page to the
    // dashboard.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_81_a_refused_main_only_page_never_redirects_to_itself(): void
    {
        $response = $this->signInAdmin($this->makeAdmin())->from('/control-panel/settings')->get('/control-panel/settings');

        $this->assertDefectStillOpen('LAB-81', function () use ($response) {
            $this->assertNotSame(url('/control-panel/settings'), $response->headers->get('Location'), 'the refused page redirects to itself');
        });
    }






    // -----------------------------------------------------------
    // test_lab_82_the_searches_take_underscores_and_percents_as_typed
    // -----------------------------------------------------------
    //
    // Known bug LAB-82 (low): the account and challenge
    // searches put the words searched for straight into a LIKE
    // pattern (Admins\UsersController::index,
    // Admins\ChallengesController::index), where _ and % match
    // any one character and any text: a search for john_doe
    // lists johnxdoe as well, and one for % lists everything.
    // Here a search for a name with an underscore also lists
    // its look-alike. Fix: escape \, % and _ in the words.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_82_the_searches_take_underscores_and_percents_as_typed(): void
    {
        $this->makePlayer(['username' => 'zq_lab82']);
        $this->makePlayer(['username' => 'zqxlab82']);
        $this->makeChallenge(['name' => 'Kq_lab82 task']);
        $this->makeChallenge(['name' => 'Kqxlab82 task']);

        $accounts = $this->signInAdmin($this->makeAdmin(true))->get('/control-panel/users?q=zq_lab82');
        $challenges = $this->get('/control-panel/challenges?q=Kq_lab82');

        $accounts->assertSee('zq_lab82');
        $challenges->assertSee('Kq_lab82 task');
        $this->assertDefectStillOpen('LAB-82', function () use ($accounts, $challenges) {
            $this->assertStringNotContainsString('zqxlab82', $accounts->getContent(), 'the account search for zq_lab82 lists zqxlab82 too');
            $this->assertStringNotContainsString('Kqxlab82 task', $challenges->getContent(), 'the challenge search for Kq_lab82 lists Kqxlab82 too');
        });
    }






    // -----------------------------------------------------------
    // test_lab_83_a_challenge_deleted_meanwhile_is_never_placed_in_a_group
    // -----------------------------------------------------------
    //
    // Known bug LAB-83 (low):
    // Admins\UsersGroupsController::challenges_store checks
    // that the challenge exists before its transaction, which
    // locks only the group, so a challenge another admin
    // deletes in between (Admins\ChallengesController::destroy,
    // which takes the group placements with it) is placed all
    // the same: the group keeps a placement of a challenge
    // that is gone, a nameless row of its challenge list. Here
    // the challenge is deleted just after the form's check
    // found it. Fix: lock the challenge's row in the
    // transaction, and check it there.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_83_a_challenge_deleted_meanwhile_is_never_placed_in_a_group(): void
    {
        $admin = $this->makeAdmin(true);
        $group = $this->makeGroup($admin);
        $challenge = $this->makeChallenge();
        $this->signInAdmin($admin)->from("/control-panel/users/vu-groups/challenges_list/{$group->id}");
        $interleaved = false;
        DB::listen(function (QueryExecuted $query) use ($challenge, &$interleaved) {
            if (!$interleaved && str_starts_with($query->sql, 'select count(*) as `aggregate` from `cyber_challenges`')) {
                $interleaved = true;
                app(ChallengesController::class)->destroy($challenge->id);
            }
        });

        $this->post('/control-panel/dialog/users/vu-groups/challenges_store', ['uid' => $group->id, 'challenge_id' => $challenge->id]);

        $this->assertTrue($interleaved);
        $this->assertNull(Challenges::find($challenge->id));
        $this->assertDefectStillOpen('LAB-83', function () use ($challenge) {
            $this->assertSame(0, GroupsChallenges::where('challenge_id', $challenge->id)->count(), 'the group keeps a placement of the deleted challenge');
        });
    }
}
