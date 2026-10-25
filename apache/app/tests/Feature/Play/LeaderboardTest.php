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

use App\Http\Controllers\Users\SettingsController;
use App\Models\ChallengesHistory;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
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
    // least one solve appear. The solver is looked for past
    // the sidebar, whose feed names them too.
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

        $this->assertStringContainsString('Solver', $this->pageContent($response));
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
        $this->recordSolveSecondsAgo($this->makePlayer(), $this->makeChallenge(), 3 * 86400);
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
        $this->recordSolveSecondsAgo($player, $this->makeChallenge(), 5 * 86400);
        $this->recordSolveSecondsAgo($player, $this->makeChallenge(), 2 * 3600);
        $this->recordSolveSecondsAgo($player, $this->makeChallenge(), 10 * 86400);

        $response = $this->signIn($this->makePlayer())->get('/user/leaderboard');

        $response->assertSeeInOrder(['Last challenge solved', '2 hours ago']);
        $this->assertStringNotContainsString('5 days ago', $this->pageContent($response));
        $this->assertStringNotContainsString('1 week ago', $this->pageContent($response));
    }






    // -----------------------------------------------------------
    // test_the_last_solve_is_counted_from_its_unix_time
    // -----------------------------------------------------------
    //
    // How long ago the last solve was is counted from its Unix
    // time, which stays right in the hour the clocks repeat in
    // October, not from its local time, which then reads an
    // hour early.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_last_solve_is_counted_from_its_unix_time(): void
    {
        $this->recordSolveInTheRepeatedHour($this->makePlayer(), $this->makeChallenge());

        $response = $this->signIn($this->makePlayer())->get('/user/leaderboard');

        $this->assertStringContainsString('<h3 class="h6 fw-normal text-dark">10 minutes ago</h3>', $this->pageContent($response));
        $this->assertStringNotContainsString('3 hours ago', $this->pageContent($response));
    }






    // -----------------------------------------------------------
    // test_the_sidebar_counts_a_solve_from_its_unix_time
    // -----------------------------------------------------------
    //
    // The feed of the sidebar counts how long ago a solve was
    // from its Unix time too.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_sidebar_counts_a_solve_from_its_unix_time(): void
    {
        $this->recordSolveInTheRepeatedHour($this->makePlayer(), $this->makeChallenge());

        $response = $this->signIn($this->makePlayer())->get('/user/leaderboard');

        $response->assertSee('<span class="timestamp text-muted">10 minutes ago</span>', false);
        $response->assertDontSee('<span class="timestamp text-muted">3 hours ago</span>', false);
    }






    // -----------------------------------------------------------
    // test_a_tie_goes_to_who_got_there_first
    // -----------------------------------------------------------
    //
    // Players with equal points are ranked by who reached the
    // score first, whose last solve was booked first, on the
    // page and in the places the sidebar shows. The booking
    // order decides, not the clock, which repeats an hour when
    // the clocks go back in October: here the solve booked first
    // carries the later time, as it does in that hour.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_tie_goes_to_who_got_there_first(): void
    {
        $late = $this->makePlayer(['username' => 'Late']);
        $early = $this->makePlayer(['username' => 'Early']);
        $this->recordSolve($early, $this->makeChallenge(['points' => 10]), '2026-10-25 03:50:00');
        $this->recordSolve($late, $this->makeChallenge(['points' => 10]), '2026-10-25 03:20:00');

        $response = $this->signIn($this->makePlayer())->get('/user/leaderboard');

        $response->assertSeeInOrder(['1st place', 'Early', '2nd place', 'Late']);
        $ranking = json_decode(Redis::get('ranking_table'), true);
        $this->assertSame(1, $ranking[$early->id]['user_rank']);
        $this->assertSame(2, $ranking[$late->id]['user_rank']);
    }






    // -----------------------------------------------------------
    // test_the_player_pages_let_the_browser_keep_the_script
    // -----------------------------------------------------------
    //
    // The player layout links its script bundle with the
    // file's time in the address, which changes only when the
    // file does, so the browser keeps the bundle from page to
    // page instead of fetching it again each time.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_player_pages_let_the_browser_keep_the_script(): void
    {
        $this->signIn($this->makePlayer())
            ->get('/user/leaderboard')
            ->assertSee('/assets/js/app.min.js?' . filemtime(public_path('assets/js/app.min.js')), false);
    }






    // -----------------------------------------------------------
    // test_the_ranking_copy_reads_no_history
    // -----------------------------------------------------------
    //
    // The ranking copy UpdateRankingTable rebuilds before
    // every request reads no history: the history table has no
    // index on the player, so grouping it would read all of it
    // on every request. The leaderboards look up the times
    // they show themselves.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_ranking_copy_reads_no_history(): void
    {
        $this->recordSolve($this->makePlayer(), $this->makeChallenge());
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries) {
            $queries[] = $query->sql;
        });

        SettingsController::update_rank_table();

        $this->assertNotEmpty($queries);
        foreach ($queries as $sql) {
            $this->assertStringNotContainsString('cyber_challenges_history', $sql);
        }
    }






    // -----------------------------------------------------------
    // test_a_solve_without_history_shows_its_local_time
    // -----------------------------------------------------------
    //
    // A solve without a history row — which no answer leaves,
    // but older data may — shows how long ago it was by its
    // local time, rather than breaking the page. The test runs
    // in UTC (inUtc), as a local time read back the night the
    // clocks go back may be an hour off.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_solve_without_history_shows_its_local_time(): void
    {
        $this->inUtc();
        $player = $this->makePlayer();
        $this->recordSolve($player, $this->makeChallenge(), date('Y-m-d H:i:s', time() - 7200));
        ChallengesHistory::where(['user_id' => $player->id])->delete();

        $response = $this->signIn($this->makePlayer())->get('/user/leaderboard');

        $response->assertOk();
        $this->assertStringContainsString('<h3 class="h6 fw-normal text-dark">2 hours ago</h3>', $this->pageContent($response));
    }






    // -----------------------------------------------------------
    // test_a_solve_worth_no_points_never_loses_a_tie
    // -----------------------------------------------------------
    //
    // Players with the same points rank by who got there
    // first: a challenge worth nothing — a rules or welcome
    // task — solved after reaching the total does not drop
    // the player behind one who reached it later. Players
    // whose solves all gave nothing rank by the first of them,
    // so a second such solve loses no tie at no points either.
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
        $this->recordSolve($early, $ten);
        $this->recordSolve($late, $ten);
        $this->recordSolve($early, $rules);
        $this->recordSolve($zeroFirst, $rules);
        $this->recordSolve($zeroLater, $rules);
        $this->recordSolve($zeroFirst, $welcome);

        $page = $this->pageContent($this->signIn($early)->get('/user/leaderboard'));

        $this->assertMatchesRegularExpression('/EarlyBird.*LateComer.*ZeroFirst.*ZeroLater/s', $page);
    }
}
