<?php

// -----------------------------------------------------------
//  [*] DashboardTest — the control panel's front page
//
//  Four counters — registered accounts, accounts that ever
//  signed in, accounts with at least one solve, VU groups —
//  the fifteen latest solves from the history table, and the
//  top ten of the ranking table the middleware keeps in
//  Redis.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature\Admin;

use Tests\TestCase;








// -----------------------------------------------------------
// DashboardTest
// -----------------------------------------------------------
//
// The database starts without accounts or groups, so the
// counters count exactly what each test makes.
//
// Used by:
//   - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------

final class DashboardTest extends TestCase
{






    // -----------------------------------------------------------
    // test_the_counters_add_up
    // -----------------------------------------------------------
    //
    // Three accounts, two of which ever signed in and one of
    // which solved something, and two groups.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_counters_add_up(): void
    {
        $solver = $this->makePlayer(['last_login' => '2026-01-10 10:00:00']);
        $this->recordSolve($solver, $this->makeChallenge());
        $this->makePlayer(['last_login' => '2026-01-11 10:00:00']);
        $this->makePlayer();
        $this->makeGroup();
        $this->makeGroup();

        $response = $this->signInAdmin($this->makeAdmin())->get('/control-panel/dashboard');

        $response->assertOk();
        $response->assertViewIs('admins.dashboard');
        $response->assertSeeInOrder([
            'Registered<br>Users', '<strong class="text-lg">3</strong>',
            'Logged in<br>Users', '<strong class="text-lg">2</strong>',
            'Solved at least<br>one challenge', '<strong class="text-lg">1</strong>',
            'Total<br>groups', '<strong class="text-lg">2</strong>',
        ], false);
    }






    // -----------------------------------------------------------
    // test_the_latest_solves_are_listed_newest_first
    // -----------------------------------------------------------
    //
    // Each solve shows the challenge, its points, the player
    // and how long ago it happened.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_latest_solves_are_listed_newest_first(): void
    {
        $player = $this->makePlayer(['username' => 'FastSolver']);
        $this->recordSolve($player, $this->makeChallenge(['name' => 'Older Task', 'points' => 10]), date('Y-m-d H:i:s', strtotime('-3 days')));
        $this->recordSolve($player, $this->makeChallenge(['name' => 'Newer Task', 'points' => 25]), date('Y-m-d H:i:s', strtotime('-2 hours')));

        $response = $this->signInAdmin($this->makeAdmin())->get('/control-panel/dashboard');

        $response->assertSee('Latest solved challenges');
        $response->assertSeeInOrder([
            '<td>Newer Task</td>', '<td>25</td>', '<td>FastSolver</td>', '2 hours ago',
            '<td>Older Task</td>', '<td>10</td>', '<td>FastSolver</td>', '3 days ago',
        ], false);
    }






    // -----------------------------------------------------------
    // test_the_latest_solves_stop_at_fifteen
    // -----------------------------------------------------------
    //
    // Of sixteen solves the oldest drops off the list.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_latest_solves_stop_at_fifteen(): void
    {
        $player = $this->makePlayer();
        foreach (range(1, 16) as $n) {
            $this->recordSolve($player, $this->makeChallenge(['name' => sprintf('Task %02d', $n)]));
        }

        $response = $this->signInAdmin($this->makeAdmin())->get('/control-panel/dashboard');

        $response->assertSee('<td>Task 16</td>', false);
        $response->assertSee('<td>Task 02</td>', false);
        $response->assertDontSee('<td>Task 01</td>', false);
    }






    // -----------------------------------------------------------
    // test_the_top_ten_players_are_shown
    // -----------------------------------------------------------
    //
    // Of eleven ranked players the ten best are shown, best
    // first with ordinal places; the eleventh is not — though
    // its solve still shows among the latest solves.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_top_ten_players_are_shown(): void
    {
        $names = ['Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo', 'Foxtrot', 'Golf', 'Hotel', 'India', 'Juliett', 'Kilo'];
        foreach ($names as $n => $name) {
            $this->recordSolve($this->makePlayer(['username' => $name]), $this->makeChallenge(['points' => 110 - 10 * $n]));
        }

        $response = $this->signInAdmin($this->makeAdmin())->get('/control-panel/dashboard');

        $response->assertSee('Top 10 players');
        $response->assertSeeInOrder(['1st place', 'Alpha', '<b>110</b> points', '2nd place', 'Bravo', '10th place', 'Juliett'], false);
        $response->assertDontSee('11th place');
        $response->assertDontSee('<h3 class="h2 fw-normal text-dark mb-0">Kilo</h3>', false);
        $response->assertSee('<td>Kilo</td>', false);
    }






    // -----------------------------------------------------------
    // test_an_empty_lab_shows_zeroes
    // -----------------------------------------------------------
    //
    // With nothing registered every counter says 0 and the
    // latest-solves card is left out.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_empty_lab_shows_zeroes(): void
    {
        $response = $this->signInAdmin($this->makeAdmin())->get('/control-panel/dashboard');

        $response->assertOk();
        $response->assertSeeInOrder(array_fill(0, 4, '<strong class="text-lg">0</strong>'), false);
        $response->assertDontSee('Latest solved challenges');
    }
}
