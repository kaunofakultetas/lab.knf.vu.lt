<?php

// -----------------------------------------------------------
//  [*] GroupTest — who may enter a VU group, and its ranking
//
//  A VU group (a lab) lists members by VU user id, so only
//  SSO accounts can belong to one. The group.exists
//  middleware answers 404 to anyone else — non-members,
//  local accounts, unknown groups — before the group's pages
//  run. The group leaderboard ranks everyone who solved at
//  least one of the group's challenges; positions and points
//  are the players' totals across the whole lab, not their
//  group points.
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
    // test_the_group_leaderboard_ranks_whoever_solved_its_challenges
    // -----------------------------------------------------------
    //
    // Everyone who solved one of the group's challenges is
    // listed, ordered by total points with ordinal positions;
    // a player who solved only challenges outside the group is
    // not.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_group_leaderboard_ranks_whoever_solved_its_challenges(): void
    {
        $group = $this->makeGroup();
        $inGroup = $this->makeChallenge(['points' => 10]);
        $outside = $this->makeChallenge(['points' => 100]);
        $this->assignToGroup($group, $inGroup);
        $steady = $this->makeSsoPlayer(['username' => 'Steady']);
        $this->recordSolve($steady, $inGroup);
        $strong = $this->makeSsoPlayer(['username' => 'Strong']);
        $this->recordSolve($strong, $inGroup);
        $this->recordSolve($strong, $outside);
        $this->recordSolve($this->makeSsoPlayer(['username' => 'Elsewhere']), $outside);
        $this->joinGroup($group, $steady);

        $response = $this->signIn($steady)->get("/user/group/leaderboard/{$group->public_id}");

        $response->assertSeeInOrder(['1st place', 'Strong', '<b>110</b> points', '2nd place', 'Steady', '<b>10</b> points'], false);
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
}
