<?php

// -----------------------------------------------------------
//  [*] AdminDefectsTest — open defects of the control panel
//
//  Each test states the behaviour the lab wants where the
//  control panel falls short today, under a LAB-nn id;
//  KnownDefects reports it incomplete while the defect is
//  open and fails it once a fix lands. The banner of every
//  test gives the severity, the mechanism, naming its files
//  and methods, and the fix.
//
//  Used by:
//    - PHPUnit — the Defects suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Defects;

use App\Http\Controllers\Admins\ChallengesController;
use App\Http\Controllers\Admins\UsersGroupsController;
use App\Models\ChallengesCategories;
use App\Models\Groups;
use App\Models\GroupsUsers;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\Support\KnownDefects;
use Tests\TestCase;








// -----------------------------------------------------------
// AdminDefectsTest
// -----------------------------------------------------------
//
// LAB-37, LAB-48, LAB-49, LAB-50 and LAB-51. The race tests
// run the other request in full at the moment it would land
// live, from a DB::listen hook, as the Feature suite's race
// tests do.
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
    // test_lab_37_a_panel_page_puts_no_address_text_into_its_scripts
    // -----------------------------------------------------------
    //
    // Known bug LAB-37 (high): the add buttons of the members,
    // group challenges and challenge files pages
    // (admins/users-groups-userlist,
    // admins/users-groups-challengeslist and
    // admins/challenges_files) print the group or challenge id
    // from the address into an inline onClick, as
    // UsersGroupsController's users_list and challenges_list
    // and Admins\ChallengesController::file_list hand the
    // address's text on. r_exists' group_owned and
    // challenge_owned checks let text such as "5);alert(1);x("
    // through, because MySQL reads it as 5 against the INT id,
    // and Blade's escaping does not cover a script in an
    // attribute. So a link a player sends an admin runs the
    // player's script with the admin's session as soon as the
    // admin clicks the page's add button. Fix: let those
    // routes take digits only (whereNumber), so other text is
    // a 404.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_37_a_panel_page_puts_no_address_text_into_its_scripts(): void
    {
        $group = $this->makeGroup();
        $challenge = $this->makeChallenge();
        $payload = ');alert(1);x(';
        $this->signInAdmin($this->makeAdmin(true));

        $bodies = [];
        foreach ([
            "/control-panel/users/vu-groups/users_list/{$group->id}{$payload}",
            "/control-panel/users/vu-groups/challenges_list/{$group->id}{$payload}",
            "/control-panel/challenges/files/{$challenge->id}{$payload}",
        ] as $page) {
            $bodies[$page] = $this->get($page)->getContent();
        }

        $this->assertDefectStillOpen('LAB-37', function () use ($bodies, $payload) {
            foreach ($bodies as $page => $body) {
                $this->assertStringNotContainsString($payload, $body, $page);
            }
        });
    }






    // -----------------------------------------------------------
    // test_lab_48_a_deleted_accounts_vote_leaves_the_counts
    // -----------------------------------------------------------
    //
    // Known bug LAB-48 (low): Admins\UsersController::destroy
    // takes an account's solves along but leaves its vote in
    // the challenge's ThumbsUp_ / ThumbsDown_ count and its
    // Vote_ mark in Redis, and the mark holds 1 rather than
    // the direction (Users\ChallengesController::vote), so the
    // vote cannot be taken back. Fix: store the direction in
    // the mark, and have destroy take the account's votes off
    // the counts and drop its marks.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_48_a_deleted_accounts_vote_leaves_the_counts(): void
    {
        $challenge = $this->makeChallenge();
        $voter = $this->makePlayer();
        $this->signIn($voter)->post("/user/challenge/vote/down/{$challenge->public_id}");

        $this->signInAdmin($this->makeAdmin(true))->post("/control-panel/users/delete/{$voter->id}");

        $this->assertDefectStillOpen('LAB-48', function () use ($challenge, $voter) {
            $this->assertSame(0, (int) Redis::get("ThumbsDown_{$challenge->public_id}"));
            $this->assertNull(Redis::get("Vote_{$challenge->public_id}_{$voter->id}"));
        });
    }






    // -----------------------------------------------------------
    // test_lab_49_a_category_save_without_a_list_keeps_the_categories
    // -----------------------------------------------------------
    //
    // Known bug LAB-49 (low):
    // Admins\ChallengesController::categories_store deletes
    // the challenge's categories before it looks at what was
    // sent, then counts the item field as a list. Sent as a
    // single value, which only a hand-made request does, it
    // throws a TypeError: a server error, after the categories
    // are gone. Fix: check that item is a list of existing
    // category ids first, then replace the categories.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_49_a_category_save_without_a_list_keeps_the_categories(): void
    {
        $challenge = $this->makeChallenge();
        ChallengesCategories::create(['challenge_id' => $challenge->id, 'cat_id' => 1]);

        $response = $this->signInAdmin($this->makeAdmin(true))
            ->from('/control-panel/challenges')
            ->post('/control-panel/challenges/categories/store', ['uid' => $challenge->id, 'item' => 'x']);

        $this->assertDefectStillOpen('LAB-49', function () use ($response, $challenge) {
            $response->assertRedirect('/control-panel/challenges');
            $this->assertSame(1, ChallengesCategories::where('challenge_id', $challenge->id)->count());
        });
    }






    // -----------------------------------------------------------
    // test_lab_50_a_double_clicked_category_save_stores_each_category_once
    // -----------------------------------------------------------
    //
    // Known bug LAB-50 (low):
    // Admins\ChallengesController::categories_store deletes
    // the challenge's categories and inserts the ticked ones
    // as separate statements, with no transaction or lock, and
    // cyber_challenges_categories has no unique key. A double
    // click on the dialog's Submit whose second delete runs
    // before the first one's inserts keeps both sets, and the
    // players' card then reads "OSINT, OSINT". Fix: lock the
    // challenge's row in a transaction around the delete and
    // the inserts, as the group adds do.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_50_a_double_clicked_category_save_stores_each_category_once(): void
    {
        $challenge = $this->makeChallenge();
        $form = ['uid' => $challenge->id, 'item' => [4 => 4]];
        $this->signInAdmin($this->makeAdmin(true))->from('/control-panel/challenges');
        $interleaved = false;
        DB::listen(function (QueryExecuted $query) use ($form, &$interleaved) {
            $sql = strtolower($query->sql);
            if (!$interleaved && (str_contains($sql, 'for update') || str_starts_with($sql, 'delete from `cyber_challenges_categories`'))) {
                $interleaved = true;
                app(ChallengesController::class)->categories_store(Request::create('/', 'POST', $form));
            }
        });

        $this->post('/control-panel/challenges/categories/store', $form);

        $this->assertTrue($interleaved);
        $this->assertDefectStillOpen('LAB-50', function () use ($challenge) {
            $this->assertSame(1, ChallengesCategories::where('challenge_id', $challenge->id)->count());
        });
    }






    // -----------------------------------------------------------
    // test_lab_51_a_group_deleted_meanwhile_takes_no_new_member
    // -----------------------------------------------------------
    //
    // Known bug LAB-51 (low): UsersGroupsController's
    // users_store and challenges_store lock the group's row
    // but never look at what the lock read, and its destroy
    // deletes the group's rows with no lock and no
    // transaction. A group another admin deletes between
    // r_exists' check and the add still gets the member, or
    // the challenge: a row of a group that no longer exists,
    // which the student's sidebar shows as a nameless link to
    // a 404. Fix: refuse the add with "No group!" when the
    // locked read finds no group, and have destroy lock the
    // group's row in a transaction around its deletes.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_51_a_group_deleted_meanwhile_takes_no_new_member(): void
    {
        $group = $this->makeGroup();
        $this->signInAdmin($this->makeAdmin(true))->from("/control-panel/users/vu-groups/users_list/{$group->id}");
        $interleaved = false;
        DB::listen(function (QueryExecuted $query) use ($group, &$interleaved) {
            if (!$interleaved && str_starts_with($query->sql, 'select count(*) as aggregate from `cyber_groups`')) {
                $interleaved = true;
                app(UsersGroupsController::class)->destroy($group->id);
            }
        });

        $this->post('/control-panel/dialog/users/vu-groups/users_store', ['uid' => $group->id, 'vu_id' => 's1234567']);

        $this->assertTrue($interleaved);
        $this->assertNull(Groups::find($group->id));
        $this->assertDefectStillOpen('LAB-51', function () use ($group) {
            $this->assertSame(0, GroupsUsers::where('group_id', $group->id)->count());
        });
    }
}
