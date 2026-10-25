<?php

// -----------------------------------------------------------
//  [*] GroupsTest — VU groups (labs) in the control panel
//
//  An admin creates groups, fills them with members by VU
//  user id and with challenges, and follows each group's
//  leaderboard. A main admin sees and manages every group; a
//  lab admin only the groups it created — the r_exists
//  middleware sends it back with an error from anyone
//  else's, on the pages that name the group in the address,
//  on the forms that post its id, and on the removal of a
//  single member or challenge.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature\Admin;

use App\Http\Controllers\Admins\ChallengesController;
use App\Http\Controllers\Admins\UsersGroupsController;
use App\Models\Challenges;
use App\Models\Groups;
use App\Models\GroupsChallenges;
use App\Models\GroupsUsers;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;








// -----------------------------------------------------------
// GroupsTest
// -----------------------------------------------------------
//
// The database starts without groups.
//
// Used by:
//   - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------

final class GroupsTest extends TestCase
{

    // The refusal the r_exists middleware flashes
    private const REFUSAL = "You don't have permissions to do this!";






    // -----------------------------------------------------------
    // test_main_admins_see_every_group
    // -----------------------------------------------------------
    //
    // A main admin's list holds every admin's groups, newest
    // first, each linking its members, its challenges and its
    // leaderboard.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_main_admins_see_every_group(): void
    {
        $this->makeGroup(null, ['name' => 'Older Lab']);
        $newer = $this->makeGroup($this->makeAdmin(), ['name' => 'Newer Lab']);

        $response = $this->signInAdmin($this->makeAdmin(true))->get('/control-panel/users/vu-groups');

        $response->assertOk();
        $response->assertViewIs('admins.users-groups');
        $response->assertSeeInOrder(['<td>Newer Lab</td>', '<td>Older Lab</td>'], false);
        $response->assertSee("href=\"/control-panel/users/vu-groups/users_list/{$newer->id}\"", false);
        $response->assertSee("href=\"/control-panel/users/vu-groups/challenges_list/{$newer->id}\"", false);
        $response->assertSee("href=\"/control-panel/users/vu-groups/leaderboard/{$newer->id}\"", false);
    }






    // -----------------------------------------------------------
    // test_lab_admins_see_only_their_own_groups
    // -----------------------------------------------------------
    //
    // A lab admin's list holds its own groups only, and says
    // so when there are none.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_admins_see_only_their_own_groups(): void
    {
        $admin = $this->makeAdmin();
        $this->makeGroup($admin, ['name' => 'My Lab']);
        $this->makeGroup(null, ['name' => 'Their Lab']);

        $this->signInAdmin($admin)->get('/control-panel/users/vu-groups')
            ->assertSee('<td>My Lab</td>', false)
            ->assertDontSee('<td>Their Lab</td>', false);
        $this->signInAdmin($this->makeAdmin())->get('/control-panel/users/vu-groups')->assertSee('No Groups');
    }






    // -----------------------------------------------------------
    // test_the_new_group_dialog_posts_a_name
    // -----------------------------------------------------------
    //
    // The dialog is a bare form posting the group's name.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_new_group_dialog_posts_a_name(): void
    {
        $response = $this->signInAdmin($this->makeAdmin())->get('/control-panel/dialog/users/vu-groups/new');

        $response->assertOk();
        $response->assertSee('action="https://lab.knf.vu.lt/control-panel/dialog/users/vu-groups/store"', false);
        $response->assertSee('name="name"', false);
    }






    // -----------------------------------------------------------
    // test_a_group_is_created_for_its_admin
    // -----------------------------------------------------------
    //
    // The group belongs to the admin who made it, gets a fresh
    // UUID as its public id and the current time, and the
    // admin returns to the list. The test runs in UTC
    // (inUtc), as a local time the app stored, read back the
    // night the clocks go back, may be an hour off.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_group_is_created_for_its_admin(): void
    {
        $this->inUtc();
        $admin = $this->makeAdmin();

        $response = $this->signInAdmin($admin)
            ->from('/control-panel/users/vu-groups')
            ->post('/control-panel/dialog/users/vu-groups/store', ['name' => 'Networks Lab 1']);

        $response->assertRedirect('/control-panel/users/vu-groups');
        $response->assertSessionHas('success', 'Created!');
        $group = Groups::where('name', 'Networks Lab 1')->firstOrFail();
        $this->assertSame($admin->id, $group->created_by);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $group->public_id);
        $this->assertEqualsWithDelta(time(), $group->created->getTimestamp(), 60);
    }






    // -----------------------------------------------------------
    // test_the_owner_renames_its_group
    // -----------------------------------------------------------
    //
    // The edit dialog carries the name and the group's id; the
    // update stores the new name.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_owner_renames_its_group(): void
    {
        $admin = $this->makeAdmin();
        $group = $this->makeGroup($admin, ['name' => 'Networks Lab 1']);
        $this->signInAdmin($admin);

        $this->get("/control-panel/dialog/users/vu-groups/edit/{$group->id}")
            ->assertSee('action="https://lab.knf.vu.lt/control-panel/dialog/users/vu-groups/update"', false)
            ->assertSee('value="Networks Lab 1"', false)
            ->assertSee("name=\"uid\" type=\"hidden\" value=\"{$group->id}\"", false);
        $this->post('/control-panel/dialog/users/vu-groups/update', ['uid' => $group->id, 'name' => 'Networks Lab 2'])
            ->assertSessionHas('success', 'Updated!');

        $this->assertSame('Networks Lab 2', $group->refresh()->name);
    }






    // -----------------------------------------------------------
    // test_a_main_admin_manages_anyones_group
    // -----------------------------------------------------------
    //
    // A main admin renames a lab admin's group like its own.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_main_admin_manages_anyones_group(): void
    {
        $group = $this->makeGroup($this->makeAdmin(), ['name' => 'Their Lab']);

        $this->signInAdmin($this->makeAdmin(true))
            ->post('/control-panel/dialog/users/vu-groups/update', ['uid' => $group->id, 'name' => 'Renamed Lab'])
            ->assertSessionHas('success', 'Updated!');

        $this->assertSame('Renamed Lab', $group->refresh()->name);
    }






    // -----------------------------------------------------------
    // test_lab_admins_cannot_touch_another_admins_group
    // -----------------------------------------------------------
    //
    // Every page and form that names someone else's group
    // sends a lab admin back with the permissions error, and
    // every dialog shows the error in its modal; the group
    // stays as it was.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_admins_cannot_touch_another_admins_group(): void
    {
        $group = $this->makeGroup(null, ['name' => 'Their Lab']);
        $challenge = $this->makeChallenge();
        $this->signInAdmin($this->makeAdmin())->from('/control-panel/users/vu-groups');
        $dialogs = [
            "/control-panel/dialog/users/vu-groups/edit/{$group->id}",
            "/control-panel/dialog/users/vu-groups/user_add_new/{$group->id}",
            "/control-panel/dialog/users/vu-groups/challenge_add_new/{$group->id}",
        ];
        $pages = [
            "/control-panel/users/vu-groups/users_list/{$group->id}",
            "/control-panel/users/vu-groups/challenges_list/{$group->id}",
            "/control-panel/users/vu-groups/leaderboard/{$group->id}",
        ];
        $forms = [
            "/control-panel/users/vu-groups/delete/{$group->id}"     => [],
            '/control-panel/dialog/users/vu-groups/update'           => ['uid' => $group->id, 'name' => 'Taken Over'],
            '/control-panel/dialog/users/vu-groups/users_store'      => ['uid' => $group->id, 'vu_id' => 's9999999'],
            '/control-panel/dialog/users/vu-groups/challenges_store' => ['uid' => $group->id, 'challenge_id' => $challenge->id],
        ];

        foreach ($dialogs as $dialog) {
            $this->assertDialogRefused($this->get($dialog), self::REFUSAL);
        }
        foreach ($pages as $page) {
            $this->get($page)->assertRedirect('/control-panel/users/vu-groups');
            $this->assertErrorFlashed(self::REFUSAL);
        }
        foreach ($forms as $form => $fields) {
            $this->post($form, $fields)->assertRedirect('/control-panel/users/vu-groups');
            $this->assertErrorFlashed(self::REFUSAL);
        }

        $this->assertSame('Their Lab', $group->refresh()->name);
        $this->assertSame(0, GroupsUsers::where('group_id', $group->id)->count());
        $this->assertSame(0, GroupsChallenges::where('group_id', $group->id)->count());
    }






    // -----------------------------------------------------------
    // test_a_group_that_does_not_exist_is_refused
    // -----------------------------------------------------------
    //
    // An id that names no group is answered with "No group!",
    // in the address and in a posted form.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_group_that_does_not_exist_is_refused(): void
    {
        $this->signInAdmin($this->makeAdmin(true))->from('/control-panel/users/vu-groups');

        $this->get('/control-panel/users/vu-groups/users_list/987654321')->assertRedirect('/control-panel/users/vu-groups');
        $this->assertErrorFlashed('No group!');
        $this->post('/control-panel/dialog/users/vu-groups/update', ['uid' => 987654321, 'name' => 'Ghost']);
        $this->assertErrorFlashed('No group!');
    }






    // -----------------------------------------------------------
    // test_deleting_a_group_removes_its_members_and_challenge_places
    // -----------------------------------------------------------
    //
    // The group goes with its members and its challenge
    // places; another group's rows and the challenges
    // themselves stay.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_deleting_a_group_removes_its_members_and_challenge_places(): void
    {
        $admin = $this->makeAdmin();
        $doomed = $this->makeGroup($admin);
        $kept = $this->makeGroup($admin);
        $challenge = $this->makeChallenge();
        $player = $this->makeSsoPlayer();
        foreach ([$doomed, $kept] as $group) {
            $this->joinGroup($group, $player);
            $this->assignToGroup($group, $challenge);
        }

        $response = $this->signInAdmin($admin)
            ->from('/control-panel/users/vu-groups')
            ->post("/control-panel/users/vu-groups/delete/{$doomed->id}");

        $response->assertRedirect('/control-panel/users/vu-groups');
        $response->assertSessionHas('success', 'Deleted!');
        $this->assertNull(Groups::find($doomed->id));
        $this->assertSame(0, GroupsUsers::where('group_id', $doomed->id)->count());
        $this->assertSame(0, GroupsChallenges::where('group_id', $doomed->id)->count());
        $this->assertSame(1, GroupsUsers::where('group_id', $kept->id)->count());
        $this->assertSame(1, GroupsChallenges::where('group_id', $kept->id)->count());
        $this->assertNotNull($challenge->fresh());
    }






    // -----------------------------------------------------------
    // test_the_member_list_shows_the_vu_ids
    // -----------------------------------------------------------
    //
    // The member list is titled with the group's name and
    // shows each member's VU user id with a remove action; an
    // empty group says it has no users.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_member_list_shows_the_vu_ids(): void
    {
        $group = $this->makeGroup(null, ['name' => 'Networks Lab 1']);
        $membership = GroupsUsers::create(['group_id' => $group->id, 'vu_id' => 's1234567']);
        $this->signInAdmin($this->makeAdmin(true));

        $this->get("/control-panel/users/vu-groups/users_list/{$group->id}")
            ->assertSee('Group - Networks Lab 1')
            ->assertSee('<td>s1234567</td>', false)
            ->assertSee("/control-panel/users/vu-groups/delete_group/{$membership->id}", false);
        $empty = $this->makeGroup();
        $this->get("/control-panel/users/vu-groups/users_list/{$empty->id}")->assertSee('No Users');
    }






    // -----------------------------------------------------------
    // test_a_member_is_added_once
    // -----------------------------------------------------------
    //
    // The add-member dialog posts a VU user id for the group;
    // the first post adds the member, a second one is refused
    // with a warning and adds nothing.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_member_is_added_once(): void
    {
        $admin = $this->makeAdmin();
        $group = $this->makeGroup($admin);
        $this->signInAdmin($admin)->from("/control-panel/users/vu-groups/users_list/{$group->id}");

        $this->get("/control-panel/dialog/users/vu-groups/user_add_new/{$group->id}")
            ->assertSee('action="https://lab.knf.vu.lt/control-panel/dialog/users/vu-groups/users_store"', false)
            ->assertSee("name=\"uid\" type=\"hidden\" value=\"{$group->id}\"", false);
        $this->post('/control-panel/dialog/users/vu-groups/users_store', ['uid' => $group->id, 'vu_id' => 's1234567'])
            ->assertSessionHas('success', 'Added!');
        $this->post('/control-panel/dialog/users/vu-groups/users_store', ['uid' => $group->id, 'vu_id' => 's1234567'])
            ->assertSessionHas('danger', 'User already added!');

        $this->assertSame(1, GroupsUsers::where(['group_id' => $group->id, 'vu_id' => 's1234567'])->count());
    }






    // -----------------------------------------------------------
    // test_a_member_is_removed
    // -----------------------------------------------------------
    //
    // The remove action deletes the one membership it names.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_member_is_removed(): void
    {
        $group = $this->makeGroup();
        $leaving = GroupsUsers::create(['group_id' => $group->id, 'vu_id' => 's1111111']);
        GroupsUsers::create(['group_id' => $group->id, 'vu_id' => 's2222222']);

        $response = $this->signInAdmin($this->makeAdmin(true))
            ->from("/control-panel/users/vu-groups/users_list/{$group->id}")
            ->post("/control-panel/users/vu-groups/delete_group/{$leaving->id}");

        $response->assertSessionHas('success', 'Deleted!');
        $this->assertSame(['s2222222'], GroupsUsers::where('group_id', $group->id)->pluck('vu_id')->all());
    }






    // -----------------------------------------------------------
    // test_the_challenge_list_shows_the_groups_challenges
    // -----------------------------------------------------------
    //
    // The challenge list is titled with the group's name and
    // shows each assigned challenge with a remove action; an
    // empty group says it has none.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_challenge_list_shows_the_groups_challenges(): void
    {
        $group = $this->makeGroup(null, ['name' => 'Networks Lab 1']);
        $place = $this->assignToGroup($group, $this->makeChallenge(['name' => 'Packet Detective']));
        $this->signInAdmin($this->makeAdmin(true));

        $this->get("/control-panel/users/vu-groups/challenges_list/{$group->id}")
            ->assertSee('Group challenges - Networks Lab 1')
            ->assertSee('<td>Packet Detective</td>', false)
            ->assertSee("/control-panel/users/vu-groups/delete_challenge/{$place->id}", false);
        $empty = $this->makeGroup();
        $this->get("/control-panel/users/vu-groups/challenges_list/{$empty->id}")->assertSee('No Challenges');
    }






    // -----------------------------------------------------------
    // test_the_add_challenge_dialog_offers_what_is_not_in_yet
    // -----------------------------------------------------------
    //
    // The dialog offers every challenge — hidden ones too —
    // except those already in the group.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_add_challenge_dialog_offers_what_is_not_in_yet(): void
    {
        $group = $this->makeGroup();
        $in = $this->makeChallenge(['name' => 'Already In']);
        $hidden = $this->makeChallenge(['name' => 'Hidden Task', 'hidden' => 1]);
        $this->assignToGroup($group, $in);

        $response = $this->signInAdmin($this->makeAdmin(true))->get("/control-panel/dialog/users/vu-groups/challenge_add_new/{$group->id}");

        $response->assertSee('action="https://lab.knf.vu.lt/control-panel/dialog/users/vu-groups/challenges_store"', false);
        $response->assertSee("<option value=\"{$hidden->id}\">Hidden Task</option>", false);
        $response->assertDontSee('Already In');
    }






    // -----------------------------------------------------------
    // test_a_challenge_is_added_once
    // -----------------------------------------------------------
    //
    // The first post puts the challenge in the group; a second
    // one is refused with a warning and adds nothing.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_challenge_is_added_once(): void
    {
        $admin = $this->makeAdmin();
        $group = $this->makeGroup($admin);
        $challenge = $this->makeChallenge();
        $this->signInAdmin($admin)->from("/control-panel/users/vu-groups/challenges_list/{$group->id}");

        $this->post('/control-panel/dialog/users/vu-groups/challenges_store', ['uid' => $group->id, 'challenge_id' => $challenge->id])
            ->assertSessionHas('success', 'Added!');
        $this->post('/control-panel/dialog/users/vu-groups/challenges_store', ['uid' => $group->id, 'challenge_id' => $challenge->id])
            ->assertSessionHas('danger', 'Challenge already added!');

        $this->assertSame(1, GroupsChallenges::where(['group_id' => $group->id, 'challenge_id' => $challenge->id])->count());
    }






    // -----------------------------------------------------------
    // test_a_lab_admin_builds_its_group_from_the_shared_catalogue
    // -----------------------------------------------------------
    //
    // The admins share one catalogue of challenges, so a lab
    // admin's dialog offers another lab admin's challenge,
    // hidden or not, and adds it to the lab admin's own group;
    // the challenge itself stays hidden from the lab-wide
    // dashboard.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_lab_admin_builds_its_group_from_the_shared_catalogue(): void
    {
        $owner = $this->makeAdmin();
        $group = $this->makeGroup($owner);
        $theirs = $this->makeChallenge(['name' => 'Their Draft', 'hidden' => 1, 'created_by' => $this->makeAdmin()->id]);
        $this->signInAdmin($owner)->from("/control-panel/users/vu-groups/challenges_list/{$group->id}");

        $this->get("/control-panel/dialog/users/vu-groups/challenge_add_new/{$group->id}")
            ->assertSee("<option value=\"{$theirs->id}\">Their Draft</option>", false);
        $this->post('/control-panel/dialog/users/vu-groups/challenges_store', ['uid' => $group->id, 'challenge_id' => $theirs->id])
            ->assertSessionHas('success', 'Added!');

        $this->assertSame(1, GroupsChallenges::where(['group_id' => $group->id, 'challenge_id' => $theirs->id])->count());
        $this->assertSame(1, $theirs->fresh()->hidden);
    }






    // -----------------------------------------------------------
    // test_a_challenge_is_taken_off_the_group
    // -----------------------------------------------------------
    //
    // The remove action deletes the group place it names; the
    // challenge itself stays.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_challenge_is_taken_off_the_group(): void
    {
        $group = $this->makeGroup();
        $challenge = $this->makeChallenge();
        $place = $this->assignToGroup($group, $challenge);

        $this->signInAdmin($this->makeAdmin(true))
            ->from("/control-panel/users/vu-groups/challenges_list/{$group->id}")
            ->post("/control-panel/users/vu-groups/delete_challenge/{$place->id}")
            ->assertSessionHas('success', 'Deleted!');

        $this->assertNull(GroupsChallenges::find($place->id));
        $this->assertNotNull($challenge->fresh());
    }






    // -----------------------------------------------------------
    // test_the_group_leaderboard_ranks_the_members
    // -----------------------------------------------------------
    //
    // The admin's view of a group's leaderboard ranks the
    // group's members by the points its challenges gave them,
    // as the players' own view does; a solver who is not a
    // member is left out.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_group_leaderboard_ranks_the_members(): void
    {
        $group = $this->makeGroup(null, ['name' => 'Networks Lab 1']);
        $small = $this->makeChallenge(['points' => 20]);
        $big = $this->makeChallenge(['points' => 30]);
        $this->assignToGroup($group, $small);
        $this->assignToGroup($group, $big);
        $second = $this->makeSsoPlayer(['username' => 'Second']);
        $this->recordSolve($second, $small);
        $first = $this->makeSsoPlayer(['username' => 'First']);
        $this->recordSolve($first, $small);
        $this->recordSolve($first, $big);
        $this->recordSolve($this->makeSsoPlayer(['username' => 'Outsider']), $big);
        $this->joinGroup($group, $first);
        $this->joinGroup($group, $second);

        $response = $this->signInAdmin($this->makeAdmin(true))->get("/control-panel/users/vu-groups/leaderboard/{$group->id}");

        $response->assertOk();
        $response->assertSee('Networks Lab 1 Leaderboard');
        $response->assertSeeInOrder(['1st place', 'First', '<b>50</b> points', '2nd place', 'Second', '<b>20</b> points'], false);
        $response->assertDontSee('Outsider');
    }






    // -----------------------------------------------------------
    // test_lab_admins_cannot_empty_other_admins_groups
    // -----------------------------------------------------------
    //
    // A lab admin cannot remove the members or challenges of a
    // group another admin owns, even by posting to the remove
    // buttons' own addresses.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_admins_cannot_empty_other_admins_groups(): void
    {
        $group = $this->makeGroup($this->makeAdmin());
        $membership = GroupsUsers::create(['group_id' => $group->id, 'vu_id' => 's1234567']);
        $place = $this->assignToGroup($group, $this->makeChallenge());
        $this->signInAdmin($this->makeAdmin())->from('/control-panel/users/vu-groups');

        $this->post("/control-panel/users/vu-groups/delete_group/{$membership->id}")->assertSessionHasErrors();
        $this->post("/control-panel/users/vu-groups/delete_challenge/{$place->id}")->assertSessionHasErrors();

        $this->assertNotNull(GroupsUsers::find($membership->id));
        $this->assertNotNull(GroupsChallenges::find($place->id));
    }






    // -----------------------------------------------------------
    // test_an_empty_group_form_is_refused
    // -----------------------------------------------------------
    //
    // The new-group dialog sent without a name goes back with the
    // error and adds no group.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_empty_group_form_is_refused(): void
    {
        $before = Groups::count();

        $response = $this->signInAdmin($this->makeAdmin(true))->from('/control-panel/users/vu-groups')->post('/control-panel/dialog/users/vu-groups/store', []);

        $response->assertRedirect('/control-panel/users/vu-groups');
        $response->assertSessionHasErrors('name');
        $this->assertSame($before, Groups::count());
    }






    // -----------------------------------------------------------
    // test_an_empty_group_rename_is_refused
    // -----------------------------------------------------------
    //
    // The rename dialog sent empty goes back with the error,
    // and the group keeps its name.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_empty_group_rename_is_refused(): void
    {
        $admin = $this->makeAdmin();
        $group = $this->makeGroup($admin, ['name' => 'Kept Lab']);

        $response = $this->signInAdmin($admin)
            ->from('/control-panel/users/vu-groups')
            ->post('/control-panel/dialog/users/vu-groups/update', ['uid' => $group->id, 'name' => '']);

        $response->assertRedirect('/control-panel/users/vu-groups');
        $response->assertSessionHasErrors('name');
        $this->assertSame('Kept Lab', $group->refresh()->name);
    }






    // -----------------------------------------------------------
    // test_a_member_needs_a_vu_id
    // -----------------------------------------------------------
    //
    // The add-member dialog sent without a VU user id goes
    // back with the error instead of a server error, and adds
    // no one.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_member_needs_a_vu_id(): void
    {
        $admin = $this->makeAdmin();
        $group = $this->makeGroup($admin);

        $response = $this->signInAdmin($admin)
            ->from("/control-panel/users/vu-groups/users_list/{$group->id}")
            ->post('/control-panel/dialog/users/vu-groups/users_store', ['uid' => $group->id, 'vu_id' => '']);

        $response->assertRedirect("/control-panel/users/vu-groups/users_list/{$group->id}");
        $response->assertSessionHasErrors('vu_id');
        $this->assertSame(0, GroupsUsers::where('group_id', $group->id)->count());
    }






    // -----------------------------------------------------------
    // test_a_group_challenge_must_exist
    // -----------------------------------------------------------
    //
    // The add-challenge dialog sent without a challenge, or
    // with an id no challenge has, goes back with the error
    // and places nothing in the group.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_group_challenge_must_exist(): void
    {
        $admin = $this->makeAdmin();
        $group = $this->makeGroup($admin);
        $this->signInAdmin($admin)->from("/control-panel/users/vu-groups/challenges_list/{$group->id}");

        $this->post('/control-panel/dialog/users/vu-groups/challenges_store', ['uid' => $group->id])
            ->assertSessionHasErrors('challenge_id');
        $this->post('/control-panel/dialog/users/vu-groups/challenges_store', ['uid' => $group->id, 'challenge_id' => 987654321])
            ->assertSessionHasErrors('challenge_id');

        $this->assertSame(0, GroupsChallenges::where('group_id', $group->id)->count());
    }






    // -----------------------------------------------------------
    // test_the_group_leaderboard_shows_the_latest_group_solve
    // -----------------------------------------------------------
    //
    // The panel's group leaderboard gives each member's latest
    // solve of the group's challenges, wherever it sits among
    // the rows.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_group_leaderboard_shows_the_latest_group_solve(): void
    {
        $group = $this->makeGroup();
        $player = $this->makeSsoPlayer();
        $this->joinGroup($group, $player);
        foreach ([5 * 86400, 2 * 3600, 10 * 86400] as $seconds) {
            $challenge = $this->makeChallenge();
            $this->assignToGroup($group, $challenge);
            $this->recordSolveSecondsAgo($player, $challenge, $seconds);
        }

        $response = $this->signInAdmin($this->makeAdmin(true))->get("/control-panel/users/vu-groups/leaderboard/{$group->id}");

        $response->assertSee('2 hours ago');
        $response->assertDontSee('5 days ago');
        $response->assertDontSee('1 week ago');
    }






    // -----------------------------------------------------------
    // test_the_group_leaderboard_counts_the_last_solve_from_its_unix_time
    // -----------------------------------------------------------
    //
    // The panel's group leaderboard counts how long ago a
    // member's last group solve was from its Unix time, which
    // stays right in the hour the clocks repeat in October.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_group_leaderboard_counts_the_last_solve_from_its_unix_time(): void
    {
        $group = $this->makeGroup();
        $player = $this->makeSsoPlayer();
        $this->joinGroup($group, $player);
        $challenge = $this->makeChallenge();
        $this->assignToGroup($group, $challenge);
        $this->recordSolveInTheRepeatedHour($player, $challenge);

        $response = $this->signInAdmin($this->makeAdmin(true))->get("/control-panel/users/vu-groups/leaderboard/{$group->id}");

        $response->assertSee('<h3 class="h6 fw-normal text-dark">10 minutes ago</h3>', false);
        $response->assertDontSee('3 hours ago');
    }






    // -----------------------------------------------------------
    // test_a_solve_with_two_history_rows_counts_once_on_the_group_leaderboard
    // -----------------------------------------------------------
    //
    // The Unix time comes from the history rows, which have no
    // unique key; on the panel's group leaderboard too, a
    // solve with two of them gives its points once.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_solve_with_two_history_rows_counts_once_on_the_group_leaderboard(): void
    {
        $group = $this->makeGroup();
        $player = $this->makeSsoPlayer();
        $this->joinGroup($group, $player);
        $challenge = $this->makeChallenge(['points' => 30]);
        $this->assignToGroup($group, $challenge);
        $this->recordSolve($player, $challenge);
        DB::table('cyber_challenges_history')->insert(['user_id' => $player->id, 'challenge_id' => $challenge->id, 'created_time' => time(), 'created' => date('Y-m-d H:i:s')]);

        $response = $this->signInAdmin($this->makeAdmin(true))->get("/control-panel/users/vu-groups/leaderboard/{$group->id}");

        $response->assertSee('<b>30</b> points', false);
        $response->assertDontSee('<b>60</b> points', false);
    }






    // -----------------------------------------------------------
    // test_a_second_add_never_doubles_a_member
    // -----------------------------------------------------------
    //
    // A member added twice at the same moment, by a double click
    // on the dialog, is stored once: the add locks the group's
    // row before it checks for the member. The second add runs
    // in full right after the first one's first step on the
    // group, where a check followed by a separate insert stored
    // the member twice.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_second_add_never_doubles_a_member(): void
    {
        $admin = $this->makeAdmin();
        $group = $this->makeGroup($admin);
        $form = ['uid' => $group->id, 'vu_id' => 's1234567'];
        $this->signInAdmin($admin)->from("/control-panel/users/vu-groups/users_list/{$group->id}");
        $interleaved = false;
        DB::listen(function (QueryExecuted $query) use ($form, &$interleaved) {
            $sql = strtolower($query->sql);
            if (!$interleaved && (str_contains($sql, 'for update') || str_contains($sql, '`cyber_users_groups`'))) {
                $interleaved = true;
                app(UsersGroupsController::class)->users_store(Request::create('/', 'POST', $form));
            }
        });

        $this->post('/control-panel/dialog/users/vu-groups/users_store', $form);

        $this->assertTrue($interleaved);
        $this->assertSame(1, GroupsUsers::where(['group_id' => $group->id, 'vu_id' => 's1234567'])->count());
    }






    // -----------------------------------------------------------
    // test_a_second_add_never_doubles_a_challenge
    // -----------------------------------------------------------
    //
    // The same for a challenge added to the group twice at the
    // same moment: it is stored once.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_second_add_never_doubles_a_challenge(): void
    {
        $admin = $this->makeAdmin();
        $group = $this->makeGroup($admin);
        $form = ['uid' => $group->id, 'challenge_id' => $this->makeChallenge()->id];
        $this->signInAdmin($admin)->from("/control-panel/users/vu-groups/challenges_list/{$group->id}");
        $interleaved = false;
        DB::listen(function (QueryExecuted $query) use ($form, &$interleaved) {
            $sql = strtolower($query->sql);
            if (!$interleaved && (str_contains($sql, 'for update') || str_contains($sql, '`cyber_groups_challenges`'))) {
                $interleaved = true;
                app(UsersGroupsController::class)->challenges_store(Request::create('/', 'POST', $form));
            }
        });

        $this->post('/control-panel/dialog/users/vu-groups/challenges_store', $form);

        $this->assertTrue($interleaved);
        $this->assertSame(1, GroupsChallenges::where(['group_id' => $group->id, 'challenge_id' => $form['challenge_id']])->count());
    }






    // -----------------------------------------------------------
    // test_a_refused_add_shows_its_warning
    // -----------------------------------------------------------
    //
    // Adding a member or a challenge the group already has
    // brings the admin back to the list with the warning on
    // screen, not just in the session.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_refused_add_shows_its_warning(): void
    {
        $admin = $this->makeAdmin();
        $group = $this->makeGroup($admin);
        $challenge = $this->makeChallenge();
        GroupsUsers::create(['group_id' => $group->id, 'vu_id' => 's1234567']);
        $this->assignToGroup($group, $challenge);
        $this->signInAdmin($admin);

        $this->from("/control-panel/users/vu-groups/users_list/{$group->id}")->followingRedirects()
            ->post('/control-panel/dialog/users/vu-groups/users_store', ['uid' => $group->id, 'vu_id' => 's1234567'])
            ->assertSee('User already added!');
        $this->from("/control-panel/users/vu-groups/challenges_list/{$group->id}")->followingRedirects()
            ->post('/control-panel/dialog/users/vu-groups/challenges_store', ['uid' => $group->id, 'challenge_id' => $challenge->id])
            ->assertSee('Challenge already added!');
    }






    // -----------------------------------------------------------
    // test_the_member_list_is_newest_first
    // -----------------------------------------------------------
    //
    // The members page lists the latest addition first, like
    // every other list of the control panel.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_member_list_is_newest_first(): void
    {
        $group = $this->makeGroup();
        foreach (['s0000001', 's0000002', 's0000003'] as $vu_id) {
            GroupsUsers::create(['group_id' => $group->id, 'vu_id' => $vu_id]);
        }

        $this->signInAdmin($this->makeAdmin(true))
            ->get("/control-panel/users/vu-groups/users_list/{$group->id}")
            ->assertSeeInOrder(['s0000003', 's0000002', 's0000001']);
    }






    // -----------------------------------------------------------
    // test_the_group_leaderboard_uses_the_panel_layout
    // -----------------------------------------------------------
    //
    // The panel's view of a group leaderboard is a control-panel
    // page: the panel's layout and script bundle, not the
    // player side's.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_group_leaderboard_uses_the_panel_layout(): void
    {
        $group = $this->makeGroup();

        $response = $this->signInAdmin($this->makeAdmin(true))->get("/control-panel/users/vu-groups/leaderboard/{$group->id}");

        $response->assertOk();
        $response->assertSee('/assets/js/app_admin.min.js', false);
        $response->assertDontSee('/assets/js/app.min.js', false);
    }






    // -----------------------------------------------------------
    // test_a_group_deleted_meanwhile_takes_no_new_member
    // -----------------------------------------------------------
    //
    // Another admin deletes the group after the ownership
    // check has found it and before the member is added. The
    // add's locked read finds no group, so it goes back with
    // "No group!" and stores no row of a group that no longer
    // exists.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_group_deleted_meanwhile_takes_no_new_member(): void
    {
        $group = $this->makeGroup();
        $this->signInAdmin($this->makeAdmin(true))->from("/control-panel/users/vu-groups/users_list/{$group->id}");
        $interleaved = false;
        DB::listen(function (QueryExecuted $query) use ($group, &$interleaved) {
            if (!$interleaved && str_starts_with($query->sql, 'select count(*) as `aggregate` from `cyber_groups`')) {
                $interleaved = true;
                app(UsersGroupsController::class)->destroy($group->id);
            }
        });

        $this->post('/control-panel/dialog/users/vu-groups/users_store', ['uid' => $group->id, 'vu_id' => 's1234567']);

        $this->assertTrue($interleaved);
        $this->assertNull(Groups::find($group->id));
        $this->assertSame(0, GroupsUsers::where('group_id', $group->id)->count());
        $this->assertErrorFlashed('No group!');
    }






    // -----------------------------------------------------------
    // test_a_group_deleted_meanwhile_takes_no_new_challenge
    // -----------------------------------------------------------
    //
    // As for a member, a challenge added to a group another
    // admin deletes meanwhile goes back with "No group!" and
    // stores no row.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_group_deleted_meanwhile_takes_no_new_challenge(): void
    {
        $group = $this->makeGroup();
        $challenge = $this->makeChallenge();
        $this->signInAdmin($this->makeAdmin(true))->from("/control-panel/users/vu-groups/challenges_list/{$group->id}");
        $interleaved = false;
        DB::listen(function (QueryExecuted $query) use ($group, &$interleaved) {
            if (!$interleaved && str_starts_with($query->sql, 'select count(*) as `aggregate` from `cyber_groups`')) {
                $interleaved = true;
                app(UsersGroupsController::class)->destroy($group->id);
            }
        });

        $this->post('/control-panel/dialog/users/vu-groups/challenges_store', ['uid' => $group->id, 'challenge_id' => $challenge->id]);

        $this->assertTrue($interleaved);
        $this->assertNull(Groups::find($group->id));
        $this->assertSame(0, GroupsChallenges::where('group_id', $group->id)->count());
        $this->assertErrorFlashed('No group!');
    }






    // -----------------------------------------------------------
    // test_a_vu_id_in_the_address_never_replaces_the_forms
    // -----------------------------------------------------------
    //
    // A VU id put into the member form's address is passed
    // over: the group gets the member the form names.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_vu_id_in_the_address_never_replaces_the_forms(): void
    {
        $group = $this->makeGroup();
        $this->signInAdmin($this->makeAdmin(true))->from("/control-panel/users/vu-groups/users_list/{$group->id}");

        $this->post('/control-panel/dialog/users/vu-groups/users_store?vu_id=s2222222', ['uid' => $group->id, 'vu_id' => 's1111111']);

        $this->assertSame(['s1111111'], GroupsUsers::where('group_id', $group->id)->pluck('vu_id')->all());
    }






    // -----------------------------------------------------------
    // test_a_deleted_groups_page_sends_the_admin_to_the_groups_list
    // -----------------------------------------------------------
    //
    // A group's page opened after another admin deleted the
    // group goes to the groups list with "No group!", even
    // when the browser comes from that same page, as after an
    // add: going back would land on the refused page again and
    // again.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_deleted_groups_page_sends_the_admin_to_the_groups_list(): void
    {
        $group = $this->makeGroup();
        $page = "/control-panel/users/vu-groups/users_list/{$group->id}";
        $this->signInAdmin($this->makeAdmin(true))->post("/control-panel/users/vu-groups/delete/{$group->id}");

        $this->from($page)->get($page)->assertRedirect('/control-panel/users/vu-groups');
        $this->assertErrorFlashed('No group!');
    }






    // -----------------------------------------------------------
    // test_a_page_past_the_end_shows_the_last_page
    // -----------------------------------------------------------
    //
    // A list asked for a page past its end shows its last
    // page, as it is the page a deletion of the last row of
    // the last page goes back to, rather than "No Groups"
    // without links to the rest.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_page_past_the_end_shows_the_last_page(): void
    {
        $this->makeGroup(null, ['name' => 'Networks Lab 1']);

        $this->signInAdmin($this->makeAdmin(true))->get('/control-panel/users/vu-groups?page=2')->assertSee('Networks Lab 1');
    }






    // -----------------------------------------------------------
    // test_a_lab_admin_removes_no_member_added_meanwhile
    // -----------------------------------------------------------
    //
    // A lab admin's removal of a membership that does not
    // exist yet passes the ownership check, as removing a row
    // already gone changes nothing. Another admin then adds a
    // row with that id to a group of their own, here right
    // after the check, and the removal leaves it alone and
    // goes back with the permissions error: the action checks
    // the admin again on the row it finds.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_lab_admin_removes_no_member_added_meanwhile(): void
    {
        $group = $this->makeGroup($this->makeAdmin(true));
        $value = 's1234567';
        $id = (int) GroupsUsers::max('id') + 1000;
        $this->signInAdmin($this->makeAdmin())->from('/control-panel/users/vu-groups');
        $interleaved = false;
        DB::listen(function (QueryExecuted $query) use ($group, $value, $id, &$interleaved) {
            if (!$interleaved && str_starts_with($query->sql, 'select `group_id` from `cyber_users_groups`')) {
                $interleaved = true;
                GroupsUsers::insert(['id' => $id, 'group_id' => $group->id, 'vu_id' => $value]);
            }
        });

        $response = $this->post("/control-panel/users/vu-groups/delete_group/{$id}");

        $this->assertTrue($interleaved);
        $response->assertRedirect('/control-panel/users/vu-groups');
        $this->assertErrorFlashed(self::REFUSAL);
        $this->assertTrue(GroupsUsers::whereKey($id)->exists());
    }






    // -----------------------------------------------------------
    // test_a_lab_admin_removes_no_group_challenge_added_meanwhile
    // -----------------------------------------------------------
    //
    // A lab admin's removal of a group challenge that does not
    // exist yet passes the ownership check, as removing a row
    // already gone changes nothing. Another admin then adds a
    // row with that id to a group of their own, here right
    // after the check, and the removal leaves it alone and
    // goes back with the permissions error: the action checks
    // the admin again on the row it finds.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_lab_admin_removes_no_group_challenge_added_meanwhile(): void
    {
        $group = $this->makeGroup($this->makeAdmin(true));
        $value = $this->makeChallenge()->id;
        $id = (int) GroupsChallenges::max('id') + 1000;
        $this->signInAdmin($this->makeAdmin())->from('/control-panel/users/vu-groups');
        $interleaved = false;
        DB::listen(function (QueryExecuted $query) use ($group, $value, $id, &$interleaved) {
            if (!$interleaved && str_starts_with($query->sql, 'select `group_id` from `cyber_groups_challenges`')) {
                $interleaved = true;
                GroupsChallenges::insert(['id' => $id, 'group_id' => $group->id, 'challenge_id' => $value]);
            }
        });

        $response = $this->post("/control-panel/users/vu-groups/delete_challenge/{$id}");

        $this->assertTrue($interleaved);
        $response->assertRedirect('/control-panel/users/vu-groups');
        $this->assertErrorFlashed(self::REFUSAL);
        $this->assertTrue(GroupsChallenges::whereKey($id)->exists());
    }






    // -----------------------------------------------------------
    // test_a_refused_group_page_goes_to_the_list
    // -----------------------------------------------------------
    //
    // A group's page refused to a lab admin — another admin's
    // group — goes to the groups list with the error, even
    // when the browser comes from that very page, which going
    // back would redirect to again and again.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_refused_group_page_goes_to_the_list(): void
    {
        $other = $this->makeGroup($this->makeAdmin(true));
        $this->signInAdmin($this->makeAdmin());

        foreach (['users_list', 'challenges_list', 'leaderboard'] as $page) {
            $address = "/control-panel/users/vu-groups/{$page}/{$other->id}";
            $this->from($address)->get($address)->assertRedirect('/control-panel/users/vu-groups');
            $this->assertErrorFlashed(self::REFUSAL);
        }
    }






    // -----------------------------------------------------------
    // test_a_refused_group_form_goes_back_and_a_dialog_shows_the_refusal
    // -----------------------------------------------------------
    //
    // A refused group form goes back to the page it was sent
    // from — here the dashboard, so going back is told apart
    // from going to the list — and a refused dialog of a
    // group, which the panel loads into the modal of the page
    // it was opened from, shows the error there, whether the
    // group is another admin's or gone.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_refused_group_form_goes_back_and_a_dialog_shows_the_refusal(): void
    {
        $other = $this->makeGroup($this->makeAdmin(true));
        $this->signInAdmin($this->makeAdmin())->from('/control-panel/dashboard');

        $this->assertDialogRefused($this->get("/control-panel/dialog/users/vu-groups/user_add_new/{$other->id}"), self::REFUSAL);
        $this->assertDialogRefused($this->get('/control-panel/dialog/users/vu-groups/user_add_new/987654321'), 'No group!');
        $this->post("/control-panel/users/vu-groups/delete/{$other->id}")->assertRedirect('/control-panel/dashboard');
        $this->assertErrorFlashed(self::REFUSAL);

        $this->assertTrue(Groups::whereKey($other->id)->exists());
    }






    // -----------------------------------------------------------
    // test_the_group_leaderboard_falls_back_to_the_local_time
    // -----------------------------------------------------------
    //
    // A solve without a history row — which no answer leaves,
    // but older data may — shows how long ago it was by its
    // local time on the panel's group leaderboard, rather than
    // breaking the page. The test runs in UTC (inUtc), as a
    // local time read back the night the clocks go back may be
    // an hour off.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_group_leaderboard_falls_back_to_the_local_time(): void
    {
        $this->inUtc();
        $group = $this->makeGroup();
        $player = $this->makeSsoPlayer();
        $this->joinGroup($group, $player);
        $challenge = $this->makeChallenge();
        $this->assignToGroup($group, $challenge);
        $this->recordSolve($player, $challenge, date('Y-m-d H:i:s', time() - 7200));
        DB::table('cyber_challenges_history')->where('user_id', $player->id)->delete();

        $response = $this->signInAdmin($this->makeAdmin(true))->get("/control-panel/users/vu-groups/leaderboard/{$group->id}");

        $response->assertOk();
        $response->assertSee('<h3 class="h6 fw-normal text-dark">2 hours ago</h3>', false);
    }






    // -----------------------------------------------------------
    // test_a_solve_worth_no_points_never_loses_a_tie
    // -----------------------------------------------------------
    //
    // The panel's group leaderboard ranks members with the
    // same points by who got there first, as the players' does:
    // a challenge worth nothing solved later loses no tie, and
    // members whose solves all gave nothing rank by the first
    // of them.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_solve_worth_no_points_never_loses_a_tie(): void
    {
        $early = $this->makeSsoPlayer(['username' => 'EarlyBird', 'display_name' => 'EarlyBird']);
        $late = $this->makeSsoPlayer(['username' => 'LateComer', 'display_name' => 'LateComer']);
        $zeroFirst = $this->makeSsoPlayer(['username' => 'ZeroFirst', 'display_name' => 'ZeroFirst']);
        $zeroLater = $this->makeSsoPlayer(['username' => 'ZeroLater', 'display_name' => 'ZeroLater']);
        $ten = $this->makeChallenge(['points' => 10]);
        $rules = $this->makeChallenge(['points' => 0]);
        $welcome = $this->makeChallenge(['points' => 0]);
        $group = $this->makeGroup();
        $this->assignToGroup($group, $ten);
        $this->assignToGroup($group, $rules);
        $this->assignToGroup($group, $welcome);
        foreach ([$early, $late, $zeroFirst, $zeroLater] as $member) {
            $this->joinGroup($group, $member);
        }
        $this->recordSolve($early, $ten);
        $this->recordSolve($late, $ten);
        $this->recordSolve($early, $rules);
        $this->recordSolve($zeroFirst, $rules);
        $this->recordSolve($zeroLater, $rules);
        $this->recordSolve($zeroFirst, $welcome);

        $page = $this->signInAdmin($this->makeAdmin(true))->get("/control-panel/users/vu-groups/leaderboard/{$group->id}")->getContent();

        $this->assertMatchesRegularExpression('/EarlyBird.*LateComer.*ZeroFirst.*ZeroLater/s', $page);
    }






    // -----------------------------------------------------------
    // test_a_challenge_deleted_meanwhile_is_never_placed
    // -----------------------------------------------------------
    //
    // A challenge another admin deletes just after the form's
    // check found it is looked up again in the transaction:
    // the placement goes back with "No challenge!", and the
    // group keeps no placement of a challenge that is gone.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_challenge_deleted_meanwhile_is_never_placed(): void
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
        $this->assertSame(0, GroupsChallenges::where('challenge_id', $challenge->id)->count());
        $this->assertErrorFlashed('No challenge!');
    }






    // -----------------------------------------------------------
    // test_a_list_for_a_field_is_refused
    // -----------------------------------------------------------
    //
    // The group forms take text and whole numbers only: a list
    // for the name, the VU id, the challenge or the group's
    // uid — only a hand-made request sends one — goes back
    // with the error and changes nothing, rather than ending
    // the save on a 500.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_list_for_a_field_is_refused(): void
    {
        $admin = $this->makeAdmin(true);
        $group = $this->makeGroup($admin, ['name' => 'Kept Name']);
        $challenge = $this->makeChallenge();
        $this->signInAdmin($admin)->from('/control-panel/users/vu-groups');

        $this->post('/control-panel/dialog/users/vu-groups/store', ['name' => ['Listed']])->assertSessionHasErrors('name');
        $this->post('/control-panel/dialog/users/vu-groups/update', ['uid' => $group->id, 'name' => ['Listed']])->assertSessionHasErrors('name');
        $this->post('/control-panel/dialog/users/vu-groups/users_store', ['uid' => $group->id, 'vu_id' => ['s1234567']])->assertSessionHasErrors('vu_id');
        $this->post('/control-panel/dialog/users/vu-groups/challenges_store', ['uid' => $group->id, 'challenge_id' => [$challenge->id]])->assertSessionHasErrors('challenge_id');
        $this->post('/control-panel/dialog/users/vu-groups/users_store', ['uid' => [$group->id], 'vu_id' => 's1234567'])->assertSessionHasErrors('uid');

        $this->assertSame('Kept Name', $group->refresh()->name);
        $this->assertSame(1, Groups::count());
        $this->assertSame(0, GroupsUsers::where('group_id', $group->id)->count());
        $this->assertSame(0, GroupsChallenges::where('group_id', $group->id)->count());
    }
}
