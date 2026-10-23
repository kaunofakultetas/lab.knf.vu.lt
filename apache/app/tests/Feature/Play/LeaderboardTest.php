<?php

// -----------------------------------------------------------
//  [*] LeaderboardTest — the ranking every player sees
//
//  Before any request is handled, the UpdateRankingTable
//  middleware reads the cyber_users_ranks view — players
//  with at least one solve, ranked by points — and stores it
//  in Redis as the ranking table, keyed by user id. The
//  leaderboard page, the player sidebar and the admin
//  dashboard all read that copy. The page lists every ranked
//  player with an ordinal place, a trophy for the top three,
//  points, an icon for individual or team accounts and the
//  time of the last solve.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature\Play;

use Illuminate\Support\Facades\Redis;
use Tests\TestCase;








// -----------------------------------------------------------
// LeaderboardTest
// -----------------------------------------------------------
//
// The players are ranked through recordSolve, which books
// solves and points the way a real answer does.
//
// Used by:
//   - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------

final class LeaderboardTest extends TestCase
{






    // -----------------------------------------------------------
    // test_players_are_ranked_by_points
    // -----------------------------------------------------------
    //
    // The ranked players appear best first, each with an
    // ordinal place, their username and their points.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_players_are_ranked_by_points(): void
    {
        $this->recordSolve($this->makePlayer(['username' => 'Bronze']), $this->makeChallenge(['points' => 10]));
        $this->recordSolve($this->makePlayer(['username' => 'Gold']), $this->makeChallenge(['points' => 50]));
        $this->recordSolve($this->makePlayer(['username' => 'Silver']), $this->makeChallenge(['points' => 30]));
        $this->signIn($this->makePlayer());

        $response = $this->get('/user/leaderboard');

        $response->assertOk();
        $response->assertViewIs('users.leaderboard');
        $response->assertSeeInOrder([
            '1st place', 'Gold', '<b>50</b> points',
            '2nd place', 'Silver', '<b>30</b> points',
            '3rd place', 'Bronze', '<b>10</b> points',
        ], false);
    }






    // -----------------------------------------------------------
    // test_the_top_three_get_trophies
    // -----------------------------------------------------------
    //
    // First, second and third place get a trophy, an award and
    // a shield; places four to ten a spy icon.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_top_three_get_trophies(): void
    {
        foreach ([40, 30, 20, 10] as $points) {
            $this->recordSolve($this->makePlayer(), $this->makeChallenge(['points' => $points]));
        }
        $this->signIn($this->makePlayer());

        $response = $this->get('/user/leaderboard');

        $response->assertSeeInOrder([
            'fa-trophy first-place', 'fa-award second-place', 'fa-shield-alt third-place', 'fa-user-secret ft-place',
        ], false);
    }






    // -----------------------------------------------------------
    // test_players_without_a_solve_are_left_out
    // -----------------------------------------------------------
    //
    // Points alone do not rank a player: only players with at
    // least one solve appear.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_players_without_a_solve_are_left_out(): void
    {
        $this->recordSolve($this->makePlayer(['username' => 'Solver']), $this->makeChallenge());
        $this->makePlayer(['username' => 'Idler', 'points' => 500]);
        $this->signIn($this->makePlayer());

        $response = $this->get('/user/leaderboard');

        $response->assertSee('Solver');
        $response->assertDontSee('Idler');
    }






    // -----------------------------------------------------------
    // test_an_empty_leaderboard_says_nobody_yet
    // -----------------------------------------------------------
    //
    // Before the first solve the page says nobody is ranked.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_empty_leaderboard_says_nobody_yet(): void
    {
        $this->signIn($this->makePlayer());

        $this->get('/user/leaderboard')->assertOk()->assertSee('Nobody yet!');
    }






    // -----------------------------------------------------------
    // test_the_ranking_table_is_rebuilt_on_every_request
    // -----------------------------------------------------------
    //
    // Any request at all — the login page included — rewrites
    // the ranking table in Redis from the database, so a new
    // solve is ranked by the next request anyone makes.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_ranking_table_is_rebuilt_on_every_request(): void
    {
        $first = $this->makePlayer();
        $second = $this->makePlayer();
        $this->recordSolve($first, $this->makeChallenge(['points' => 30]));
        $this->get('/');

        $table = json_decode(Redis::get('ranking_table'), true);
        $this->assertSame([$first->id], array_keys($table));
        $this->assertSame(1, $table[$first->id]['user_rank']);
        $this->assertSame(30, $table[$first->id]['points']);

        $this->recordSolve($second, $this->makeChallenge(['points' => 50]));
        $this->get('/');

        $table = json_decode(Redis::get('ranking_table'), true);
        $this->assertSame([$second->id, $first->id], array_keys($table));
        $this->assertSame(1, $table[$second->id]['user_rank']);
        $this->assertSame(2, $table[$first->id]['user_rank']);
        $this->assertSame($second->username, $table[$second->id]['username']);
    }






    // -----------------------------------------------------------
    // test_team_accounts_get_the_team_icon
    // -----------------------------------------------------------
    //
    // Individual accounts (type 1) show a person, team
    // accounts (type 2) a group of people.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_team_accounts_get_the_team_icon(): void
    {
        $this->recordSolve($this->makePlayer(['user_type' => 2]), $this->makeChallenge(['points' => 20]));
        $this->recordSolve($this->makePlayer(['user_type' => 1]), $this->makeChallenge(['points' => 10]));
        $this->signIn($this->makePlayer());

        $this->get('/user/leaderboard')->assertSeeInOrder(['fas fa-users', 'fas fa-user"'], false);
    }






    // -----------------------------------------------------------
    // test_the_time_of_the_last_solve_is_shown
    // -----------------------------------------------------------
    //
    // Each row tells how long ago the player solved.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_time_of_the_last_solve_is_shown(): void
    {
        $this->recordSolve($this->makePlayer(), $this->makeChallenge(), date('Y-m-d H:i:s', strtotime('-3 days')));
        $this->signIn($this->makePlayer());

        $this->get('/user/leaderboard')->assertSeeInOrder(['Last challenge solved', '3 days ago']);
    }






    // -----------------------------------------------------------
    // test_usernames_are_escaped
    // -----------------------------------------------------------
    //
    // A username carrying markup — possible for accounts an
    // admin creates, which skip the registration rules — is
    // printed as text, never as HTML.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_usernames_are_escaped(): void
    {
        $this->recordSolve($this->makePlayer(['username' => '<script>alert(1)</script>']), $this->makeChallenge());
        $this->signIn($this->makePlayer());

        $response = $this->get('/user/leaderboard');

        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $response->assertDontSee('<script>alert(1)</script>', false);
    }






    // -----------------------------------------------------------
    // test_the_viewers_groups_link_their_own_leaderboards
    // -----------------------------------------------------------
    //
    // A player in VU groups gets a link to each group's
    // leaderboard above the list.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_viewers_groups_link_their_own_leaderboards(): void
    {
        $player = $this->makeSsoPlayer();
        $group = $this->makeGroup(null, ['name' => 'Lab Alpha']);
        $this->joinGroup($group, $player);

        $response = $this->signIn($player)->get('/user/leaderboard');

        $response->assertSee("href=\"https://lab.knf.vu.lt/user/group/leaderboard/{$group->public_id}\"", false);
        $response->assertSee('Lab Alpha Leaderboard');
    }






    // -----------------------------------------------------------
    // test_the_sidebar_shows_the_viewers_place
    // -----------------------------------------------------------
    //
    // A ranked viewer finds their own place and points in the
    // sidebar, with the award icon for second place.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_sidebar_shows_the_viewers_place(): void
    {
        $viewer = $this->makePlayer();
        $this->recordSolve($this->makePlayer(), $this->makeChallenge(['points' => 90]));
        $this->recordSolve($viewer, $this->makeChallenge(['points' => 40]));

        $response = $this->signIn($viewer)->get('/user/leaderboard');

        $response->assertSee('second-place2', false);
        $response->assertSee('2nd</span> place', false);
        $response->assertSee('<span style="font-weight:bold!important;">40</span> points', false);
    }






    // -----------------------------------------------------------
    // test_the_leaderboard_shows_the_latest_solve
    // -----------------------------------------------------------
    //
    // "Last challenge solved" is the player's latest solve.
    // It sits in the middle both by row id and by challenge
    // id, so no plan that keeps the first or the last row it
    // reads can show it by chance.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_leaderboard_shows_the_latest_solve(): void
    {
        $player = $this->makePlayer();
        $this->recordSolve($player, $this->makeChallenge(), date('Y-m-d H:i:s', strtotime('-5 days')));
        $this->recordSolve($player, $this->makeChallenge(), date('Y-m-d H:i:s', strtotime('-2 hours')));
        $this->recordSolve($player, $this->makeChallenge(), date('Y-m-d H:i:s', strtotime('-10 days')));

        $response = $this->signIn($this->makePlayer())->get('/user/leaderboard');

        $response->assertSeeInOrder(['Last challenge solved', '2 hours ago']);
        $response->assertDontSee('5 days ago');
        $response->assertDontSee('1 week ago');
    }
}
