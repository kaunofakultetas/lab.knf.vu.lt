<?php

// -----------------------------------------------------------
//  [*] GroupDashboardTest — a VU group's own challenge list
//
//  A group member's view of the group: the challenges
//  assigned to it — hidden ones included, as lab admins'
//  challenges are hidden from the lab-wide dashboard — with
//  solve links that stay inside the group, and a progress bar
//  with the share of the group's points the member has
//  solved.
//
//  GroupsController::index declares the same helper function
//  as the player dashboard, so the page can be rendered only
//  once per PHP process; every test runs in a process of its
//  own and opens it exactly once.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature\Play;

use App\Models\Groups;
use App\Models\User;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;








// -----------------------------------------------------------
// GroupDashboardTest
// -----------------------------------------------------------
//
// Every test signs in an SSO player who is a member of one
// group.
//
// Used by:
//   - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class GroupDashboardTest extends TestCase
{

    // The signed-in member and the group
    private User $member;
    private Groups $group;






    // -----------------------------------------------------------
    // setUp
    // -----------------------------------------------------------
    //
    // Makes the group and signs in a member of it.
    //
    // Used by:
    //   - PHPUnit, before every test
    // -----------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();
        $this->member = $this->makeSsoPlayer();
        $this->group = $this->makeGroup(null, ['name' => 'Lab Alpha']);
        $this->joinGroup($this->group, $this->member);
        $this->signIn($this->member);
    }






    // -----------------------------------------------------------
    // test_only_the_groups_challenges_are_listed
    // -----------------------------------------------------------
    //
    // The group's challenges, hidden ones too, are listed with
    // solve links that carry the group's id; challenges of the
    // lab outside the group are not.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_only_the_groups_challenges_are_listed(): void
    {
        $hidden = $this->makeChallenge(['name' => 'Hidden Lab Task', 'hidden' => 1]);
        $public = $this->makeChallenge(['name' => 'Public Lab Task']);
        $this->makeChallenge(['name' => 'Not In This Group']);
        $this->assignToGroup($this->group, $hidden);
        $this->assignToGroup($this->group, $public);

        $response = $this->get("/user/group/{$this->group->public_id}");

        $response->assertOk();
        $response->assertViewIs('users.dashboard_group');
        $response->assertSee('Hidden Lab Task');
        $response->assertSee('Public Lab Task');
        $response->assertDontSee('Not In This Group');
        $response->assertSee("href=\"/user/challenge/{$hidden->public_id}/{$this->group->public_id}\"", false);
    }






    // -----------------------------------------------------------
    // test_the_progress_bar_shows_the_share_of_points_solved
    // -----------------------------------------------------------
    //
    // Solving the 10-point challenge of a 10 + 30 point group
    // fills the bar to 25%, and the solved card says so.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_progress_bar_shows_the_share_of_points_solved(): void
    {
        $small = $this->makeChallenge(['points' => 10]);
        $this->assignToGroup($this->group, $small);
        $this->assignToGroup($this->group, $this->makeChallenge(['points' => 30]));
        $this->recordSolve($this->member, $small);

        $response = $this->get("/user/group/{$this->group->public_id}");

        $response->assertSee('<b>25%</b>', false);
        $response->assertSee('style="width: 25%"', false);
        $response->assertSee('Challenge Solved');
    }






    // -----------------------------------------------------------
    // test_points_solved_outside_the_group_do_not_count
    // -----------------------------------------------------------
    //
    // The bar only counts the group's own challenges.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_points_solved_outside_the_group_do_not_count(): void
    {
        $this->assignToGroup($this->group, $this->makeChallenge(['points' => 30]));
        $this->recordSolve($this->member, $this->makeChallenge(['points' => 100]));

        $this->get("/user/group/{$this->group->public_id}")->assertSee('<b>0%</b>', false);
    }






    // -----------------------------------------------------------
    // test_an_empty_group_says_so
    // -----------------------------------------------------------
    //
    // A group with no challenge shows no cards and an empty
    // bar.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_empty_group_says_so(): void
    {
        $response = $this->get("/user/group/{$this->group->public_id}");

        $response->assertSee('No challenges');
        $response->assertSee('<b>0%</b>', false);
    }






    // -----------------------------------------------------------
    // test_the_page_links_the_group_leaderboard
    // -----------------------------------------------------------
    //
    // The header links the group's leaderboard.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_page_links_the_group_leaderboard(): void
    {
        $response = $this->get("/user/group/{$this->group->public_id}");

        $response->assertSee("href=\"/user/group/leaderboard/{$this->group->public_id}\"", false);
    }
}
