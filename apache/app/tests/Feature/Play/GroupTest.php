<?php

// -----------------------------------------------------------
//  [*] GroupTest — who may enter a VU group, and its ranking
//
//  A VU group (a lab) lists members by VU user id, so only
//  SSO accounts can belong to one. The group.exists
//  middleware answers 404 to anyone else — non-members,
//  local accounts, unknown groups — before the group's pages
//  run. The group leaderboard ranks the group's members by
//  the points the group's challenges gave them — the points
//  the group page counts, not their totals across the lab —
//  and leaves out everyone else.
//
//  The group dashboard itself is GroupDashboardTest's — like
//  the player dashboard, it may only be rendered once per
//  PHP process.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature\Play;

use Illuminate\Support\Str;
use Tests\TestCase;








// -----------------------------------------------------------
// GroupTest
// -----------------------------------------------------------
//
// Groups belong to the seeded main admin unless a test says
// otherwise.
//
// Used by:
//   - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------

final class GroupTest extends TestCase
{






    // -----------------------------------------------------------
    // test_a_member_opens_the_group_leaderboard
    // -----------------------------------------------------------
    //
    // A member reaches the group's leaderboard, titled with
    // the group's name.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_member_opens_the_group_leaderboard(): void
    {
        $player = $this->makeSsoPlayer();
        $group = $this->makeGroup(null, ['name' => 'Lab Alpha']);
        $this->joinGroup($group, $player);

        $response = $this->signIn($player)->get("/user/group/leaderboard/{$group->public_id}");

        $response->assertOk();
        $response->assertViewIs('users.groups_leaderboard');
        $response->assertSee('Lab Alpha Leaderboard');
    }






    // -----------------------------------------------------------
    // test_non_members_get_404
    // -----------------------------------------------------------
    //
    // An SSO player outside the group gets 404 for the group's
    // dashboard and for its leaderboard.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_non_members_get_404(): void
    {
        $group = $this->makeGroup();
        $this->joinGroup($group, $this->makeSsoPlayer());
        $this->signIn($this->makeSsoPlayer());

        $this->get("/user/group/{$group->public_id}")->assertNotFound();
        $this->get("/user/group/leaderboard/{$group->public_id}")->assertNotFound();
    }






    // -----------------------------------------------------------
    // test_a_local_account_never_gets_in
    // -----------------------------------------------------------
    //
    // A local account has no VU user id, so no group can list
    // it, and every group page is 404 to it.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_local_account_never_gets_in(): void
    {
        $group = $this->makeGroup();
        $this->joinGroup($group, $this->makeSsoPlayer());
        $this->signIn($this->makePlayer());

        $this->get("/user/group/{$group->public_id}")->assertNotFound();
        $this->get("/user/group/leaderboard/{$group->public_id}")->assertNotFound();
    }






    // -----------------------------------------------------------
    // test_an_unknown_group_is_404
    // -----------------------------------------------------------
    //
    // A public id that names no group is 404.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_unknown_group_is_404(): void
    {
        $this->signIn($this->makeSsoPlayer());

        $this->get('/user/group/' . Str::uuid())->assertNotFound();
        $this->get('/user/group/leaderboard/' . Str::uuid())->assertNotFound();
    }






    // -----------------------------------------------------------
    // test_the_group_leaderboard_ranks_its_members_by_group_points
    // -----------------------------------------------------------
    //
    // The members are listed with ordinal positions, ranked
    // by what the group's challenges gave them: points earned
    // outside the group lift no one, and a player who solved
    // a group challenge without being a member is not listed.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_group_leaderboard_ranks_its_members_by_group_points(): void
    {
        $group = $this->makeGroup();
        $first = $this->makeChallenge(['points' => 10]);
        $second = $this->makeChallenge(['points' => 20]);
        $outside = $this->makeChallenge(['points' => 100]);
        $this->assignToGroup($group, $first);
        $this->assignToGroup($group, $second);
        $steady = $this->makeSsoPlayer(['username' => 'Steady']);
        $this->recordSolve($steady, $first);
        $this->recordSolve($steady, $second);
        $strong = $this->makeSsoPlayer(['username' => 'Strong']);
        $this->recordSolve($strong, $first);
        $this->recordSolve($strong, $outside);
        $this->recordSolve($this->makeSsoPlayer(['username' => 'Elsewhere']), $first);
        $this->joinGroup($group, $steady);
        $this->joinGroup($group, $strong);

        $response = $this->signIn($steady)->get("/user/group/leaderboard/{$group->public_id}");

        $response->assertSeeInOrder(['1st place', 'Steady', '<b>30</b> points', '2nd place', 'Strong', '<b>10</b> points'], false);
        $response->assertDontSee('<b>110</b>', false);
        $response->assertDontSee('Elsewhere');
    }






    // -----------------------------------------------------------
    // test_an_unplayed_group_leaderboard_says_nobody_yet
    // -----------------------------------------------------------
    //
    // Before anyone solves a group challenge, the group
    // leaderboard says nobody is ranked.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_unplayed_group_leaderboard_says_nobody_yet(): void
    {
        $player = $this->makeSsoPlayer();
        $group = $this->makeGroup();
        $this->assignToGroup($group, $this->makeChallenge());
        $this->joinGroup($group, $player);

        $this->signIn($player)->get("/user/group/leaderboard/{$group->public_id}")->assertSee('Nobody yet!');
    }






    // -----------------------------------------------------------
    // test_the_group_leaderboard_links_the_other_leaderboards
    // -----------------------------------------------------------
    //
    // Above the list sit links to the lab-wide leaderboard and
    // to the leaderboards of the member's other groups, but
    // not to the one on screen.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_group_leaderboard_links_the_other_leaderboards(): void
    {
        $player = $this->makeSsoPlayer();
        $alpha = $this->makeGroup(null, ['name' => 'Lab Alpha']);
        $beta = $this->makeGroup(null, ['name' => 'Lab Beta']);
        $this->joinGroup($alpha, $player);
        $this->joinGroup($beta, $player);

        $response = $this->signIn($player)->get("/user/group/leaderboard/{$alpha->public_id}");

        $response->assertSee('href="https://lab.knf.vu.lt/user/leaderboard"', false);
        $response->assertSee("href=\"https://lab.knf.vu.lt/user/group/leaderboard/{$beta->public_id}\"", false);
        $response->assertDontSee("href=\"https://lab.knf.vu.lt/user/group/leaderboard/{$alpha->public_id}\"", false);
    }






    // -----------------------------------------------------------
    // test_the_group_leaderboard_shows_the_latest_group_solve
    // -----------------------------------------------------------
    //
    // A member's last solve on the group leaderboard is the
    // latest solve of the group's challenges, wherever it sits
    // among the rows.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_group_leaderboard_shows_the_latest_group_solve(): void
    {
        $group = $this->makeGroup();
        $member = $this->makeSsoPlayer();
        $this->joinGroup($group, $member);
        foreach (['-5 days', '-2 hours', '-10 days'] as $when) {
            $challenge = $this->makeChallenge();
            $this->assignToGroup($group, $challenge);
            $this->recordSolve($member, $challenge, date('Y-m-d H:i:s', strtotime($when)));
        }

        $response = $this->signIn($member)->get("/user/group/leaderboard/{$group->public_id}");

        $response->assertSee('2 hours ago');
        $response->assertDontSee('5 days ago');
        $response->assertDontSee('1 week ago');
    }






    // -----------------------------------------------------------
    // test_a_group_tie_goes_to_who_got_there_first
    // -----------------------------------------------------------
    //
    // Members with equal group points are ranked by whose last
    // group solve was booked first, whatever the clock said:
    // the hour the clocks go back in October repeats, so the
    // solve booked first may carry the later time.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_group_tie_goes_to_who_got_there_first(): void
    {
        $group = $this->makeGroup();
        $first = $this->makeChallenge(['points' => 10]);
        $second = $this->makeChallenge(['points' => 10]);
        $this->assignToGroup($group, $first);
        $this->assignToGroup($group, $second);
        $late = $this->makeSsoPlayer(['username' => 'Late']);
        $early = $this->makeSsoPlayer(['username' => 'Early']);
        $this->joinGroup($group, $late);
        $this->joinGroup($group, $early);
        $this->recordSolve($early, $first, '2026-10-25 03:50:00');
        $this->recordSolve($late, $second, '2026-10-25 03:20:00');

        $response = $this->signIn($early)->get("/user/group/leaderboard/{$group->public_id}");

        $response->assertSeeInOrder(['1st place', 'Early', '2nd place', 'Late']);
    }






    // -----------------------------------------------------------
    // test_a_same_named_group_still_gets_its_link
    // -----------------------------------------------------------
    //
    // The member's other groups are told apart by id above the
    // list, so a second group with the same name still gets its
    // link, and only the group on screen is left out.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_same_named_group_still_gets_its_link(): void
    {
        $player = $this->makeSsoPlayer();
        $mine = $this->makeGroup(null, ['name' => 'Networks Lab']);
        $twin = $this->makeGroup(null, ['name' => 'Networks Lab']);
        $this->joinGroup($mine, $player);
        $this->joinGroup($twin, $player);

        $response = $this->signIn($player)->get("/user/group/leaderboard/{$mine->public_id}");

        $response->assertSee("href=\"https://lab.knf.vu.lt/user/group/leaderboard/{$twin->public_id}\"", false);
        $response->assertDontSee("href=\"https://lab.knf.vu.lt/user/group/leaderboard/{$mine->public_id}\"", false);
    }
}
