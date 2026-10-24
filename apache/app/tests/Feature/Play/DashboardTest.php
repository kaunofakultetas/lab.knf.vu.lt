<?php

// -----------------------------------------------------------
//  [*] DashboardTest — the player's list of challenges
//
//  The dashboard lists every visible challenge as a card —
//  name, points, categories, the start of the description —
//  unsolved ones first, cheapest first within each half, with
//  a link to solve it or, once solved, the vote buttons. The
//  sidebar next to it shows the player's place and points,
//  the player's VU groups and the latest solves of everyone.
//
//  ChallengesController::index declares a helper function
//  inside the action, so the page can be rendered only once
//  per PHP process — Apache starts every request afresh, the
//  test runner would die on the second render. Every test
//  here therefore runs in a process of its own and opens the
//  dashboard exactly once.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature\Play;

use App\Models\ChallengesCategories;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;








// -----------------------------------------------------------
// DashboardTest
// -----------------------------------------------------------
//
// Each test signs in a player — a VU account where groups
// matter — sets the scene and renders the dashboard once.
//
// Used by:
//   - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class DashboardTest extends TestCase
{






    // -----------------------------------------------------------
    // test_visible_challenges_are_listed_with_their_details
    // -----------------------------------------------------------
    //
    // A card shows the name, the points, the category names,
    // the first hundred characters of the description and the
    // link to the challenge; its CSS classes carry the
    // category ids the filter buttons select by.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_visible_challenges_are_listed_with_their_details(): void
    {
        $challenge = $this->makeChallenge([
            'name'   => 'Packet Detective',
            'points' => 25,
            'descr'  => str_repeat('a', 100) . 'TRUNCATED',
        ]);
        ChallengesCategories::create(['cat_id' => 1, 'challenge_id' => $challenge->id]);
        ChallengesCategories::create(['cat_id' => 4, 'challenge_id' => $challenge->id]);
        $this->signIn($this->makePlayer());

        $response = $this->get('/user/dashboard');

        $response->assertOk();
        $response->assertViewIs('users.dashboard');
        $response->assertSee('Packet Detective');
        $response->assertSee('25 points');
        $response->assertSee('Cryptography, OSINT');
        $response->assertSee('filtered-item c1 c4', false);
        $response->assertSee(str_repeat('a', 100) . '...');
        $response->assertDontSee('TRUNCATED');
        $response->assertSee("href=\"/user/challenge/{$challenge->public_id}\"", false);
    }






    // -----------------------------------------------------------
    // test_hidden_challenges_are_left_out
    // -----------------------------------------------------------
    //
    // A hidden challenge — a lab admin's, or one a main admin
    // switched off — has no card.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_hidden_challenges_are_left_out(): void
    {
        $this->makeChallenge(['name' => 'Visible One']);
        $this->makeChallenge(['name' => 'Hidden One', 'hidden' => 1]);
        $this->signIn($this->makePlayer());

        $response = $this->get('/user/dashboard');

        $response->assertSee('Visible One');
        $response->assertDontSee('Hidden One');
    }






    // -----------------------------------------------------------
    // test_unsolved_challenges_come_first_cheapest_first
    // -----------------------------------------------------------
    //
    // The cards are sorted by points, then the unsolved ones
    // are moved in front of the solved ones without changing
    // the order inside either group.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_unsolved_challenges_come_first_cheapest_first(): void
    {
        $player = $this->makePlayer();
        $this->makeChallenge(['name' => 'Open Thirty', 'points' => 30]);
        $this->recordSolve($player, $this->makeChallenge(['name' => 'Solved Ten', 'points' => 10]));
        $this->makeChallenge(['name' => 'Open Twenty', 'points' => 20]);
        $this->recordSolve($player, $this->makeChallenge(['name' => 'Solved Five', 'points' => 5]));
        $this->signIn($player);

        $response = $this->get('/user/dashboard');

        $response->assertSeeInOrder(['Open Twenty', 'Open Thirty', 'Solved Five', 'Solved Ten']);
    }






    // -----------------------------------------------------------
    // test_a_solved_card_offers_votes_instead_of_the_link
    // -----------------------------------------------------------
    //
    // A solved challenge's card says so, drops the link to the
    // challenge and shows the vote buttons with the counts of
    // its rows in cyber_challenges_votes.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_solved_card_offers_votes_instead_of_the_link(): void
    {
        $player = $this->makePlayer();
        $challenge = $this->makeChallenge();
        $this->recordSolve($player, $challenge);
        foreach (['up', 'up', 'up', 'down', 'down'] as $vote) {
            DB::table('cyber_challenges_votes')->insert(['user_id' => $this->makePlayer()->id, 'challenge_id' => $challenge->id, 'vote' => $vote, 'created' => date('Y-m-d H:i:s')]);
        }
        $this->signIn($player);

        $response = $this->get('/user/dashboard');

        $response->assertSee('Challenge Solved');
        $response->assertDontSee("href=\"/user/challenge/{$challenge->public_id}\"", false);
        $response->assertSee("<span id=\"thumbs-up-count-{$challenge->public_id}\">3</span>", false);
        $response->assertSee("<span id=\"thumbs-down-count-{$challenge->public_id}\">2</span>", false);
        $response->assertSee("<button  class=\"badge border-0 text-white bg-primary thumbs-up-{$challenge->public_id}\"", false);
    }






    // -----------------------------------------------------------
    // test_the_vote_buttons_lock_once_the_player_voted
    // -----------------------------------------------------------
    //
    // After the player's vote — a row of the player's in
    // cyber_challenges_votes — the buttons on the card are
    // rendered disabled, the other thumb's too.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_vote_buttons_lock_once_the_player_voted(): void
    {
        $player = $this->makePlayer();
        $challenge = $this->makeChallenge();
        $this->recordSolve($player, $challenge);
        DB::table('cyber_challenges_votes')->insert(['user_id' => $player->id, 'challenge_id' => $challenge->id, 'vote' => 'down', 'created' => date('Y-m-d H:i:s')]);
        $this->signIn($player);

        $response = $this->get('/user/dashboard');

        $response->assertSee("<button disabled class=\"badge border-0 text-white bg-primary thumbs-up-{$challenge->public_id}\"", false);
        $response->assertSee("<button disabled class=\"badge border-0 bg-white text-primary thumbs-down-{$challenge->public_id} \"", false);
    }






    // -----------------------------------------------------------
    // test_answers_never_reach_the_dashboard
    // -----------------------------------------------------------
    //
    // No card gives a stored answer away.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_answers_never_reach_the_dashboard(): void
    {
        $this->makeChallenge(['answer' => 'the_secret_flag_42']);
        $this->signIn($this->makePlayer());

        $this->get('/user/dashboard')->assertOk()->assertDontSee('the_secret_flag_42');
    }






    // -----------------------------------------------------------
    // test_an_empty_dashboard_says_so
    // -----------------------------------------------------------
    //
    // With no visible challenge the page says there are none.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_empty_dashboard_says_so(): void
    {
        $this->makeChallenge(['hidden' => 1]);
        $this->signIn($this->makePlayer());

        $this->get('/user/dashboard')->assertOk()->assertSee('No challenges');
    }






    // -----------------------------------------------------------
    // test_every_category_gets_a_filter_button
    // -----------------------------------------------------------
    //
    // The filter row offers "Any" and one button per category
    // — the seven seeded ones and any added later — each
    // selecting its category's CSS class.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_every_category_gets_a_filter_button(): void
    {
        $category = $this->makeCategory('Hardware Hacking');
        $this->signIn($this->makePlayer());

        $response = $this->get('/user/dashboard');

        $response->assertSee('data-filter="">Any</button>', false);
        $response->assertSee('data-filter=".c1">Cryptography</button>', false);
        $response->assertSee('data-filter=".c7">Reverse Engineering</button>', false);
        $response->assertSee("data-filter=\".c{$category->id}\">Hardware Hacking</button>", false);
    }






    // -----------------------------------------------------------
    // test_the_sidebar_shows_the_players_place_and_points
    // -----------------------------------------------------------
    //
    // A ranked player sees the username, the place with its
    // trophy and the points in the sidebar.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_sidebar_shows_the_players_place_and_points(): void
    {
        $player = $this->makePlayer(['username' => 'TopPlayer']);
        $this->recordSolve($player, $this->makeChallenge(['points' => 50]));
        $this->recordSolve($this->makePlayer(), $this->makeChallenge(['points' => 20]));
        $this->signIn($player);

        $response = $this->get('/user/dashboard');

        $response->assertSee('<h1 class="h4 mb-2">TopPlayer</h1>', false);
        $response->assertSee('first-place2', false);
        $response->assertSee('1st</span> place', false);
        $response->assertSee('<span style="font-weight:bold!important;">50</span> points', false);
    }






    // -----------------------------------------------------------
    // test_an_unranked_player_has_no_place_in_the_sidebar
    // -----------------------------------------------------------
    //
    // Before the first solve the sidebar shows neither a place
    // nor points.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_unranked_player_has_no_place_in_the_sidebar(): void
    {
        $this->recordSolve($this->makePlayer(), $this->makeChallenge());
        $this->signIn($this->makePlayer());

        $this->get('/user/dashboard')->assertOk()->assertDontSee('</span> place', false);
    }






    // -----------------------------------------------------------
    // test_the_sidebar_lists_the_players_groups
    // -----------------------------------------------------------
    //
    // Each VU group the player belongs to gets a sidebar link
    // to its own dashboard; other groups do not appear.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_sidebar_lists_the_players_groups(): void
    {
        $player = $this->makeSsoPlayer();
        $mine = $this->makeGroup(null, ['name' => 'Lab Alpha']);
        $this->makeGroup(null, ['name' => 'Lab Beta']);
        $this->joinGroup($mine, $player);
        $this->signIn($player);

        $response = $this->get('/user/dashboard');

        $response->assertSee("href=\"https://lab.knf.vu.lt/user/group/{$mine->public_id}\"", false);
        $response->assertSee('Lab Alpha');
        $response->assertDontSee('Lab Beta');
    }






    // -----------------------------------------------------------
    // test_the_sidebar_shows_the_latest_solves
    // -----------------------------------------------------------
    //
    // A solve by anyone appears in every player's sidebar
    // history — who solved what, and how long ago.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_sidebar_shows_the_latest_solves(): void
    {
        $solver = $this->makePlayer(['username' => 'FastSolver']);
        $challenge = $this->makeChallenge(['name' => 'Packet Detective']);
        $this->signIn($solver)->post("/user/challenge/{$challenge->public_id}", ['answer' => $challenge->answer]);
        $this->signIn($this->makePlayer());

        $response = $this->get('/user/dashboard');

        $response->assertSee('History');
        $response->assertSee('<strong>FastSolver</strong> solved Packet Detective challenge', false);
    }






    // -----------------------------------------------------------
    // test_a_card_description_is_cut_by_letters
    // -----------------------------------------------------------
    //
    // The hundred characters a card shows are counted in
    // letters, not bytes, so a description whose hundredth
    // character is a Lithuanian letter keeps it, rather than
    // losing the whole text to a letter cut in half.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_card_description_is_cut_by_letters(): void
    {
        $this->makeChallenge(['descr' => str_repeat('a', 99) . 'ąčęėįšųūž']);
        $this->signIn($this->makePlayer());

        $this->get('/user/dashboard')->assertSee(str_repeat('a', 99) . 'ą...');
    }






    // -----------------------------------------------------------
    // test_the_points_filter_shows_the_pages_prices_as_hints
    // -----------------------------------------------------------
    //
    // The points filter's boxes start empty, with the cheapest
    // and the dearest challenge on the page as their
    // placeholders: an empty box is no bound, so a challenge
    // of any price shows, and the cookies that keep the filter
    // keep only bounds the player typed, never the prices of
    // the moment.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_points_filter_shows_the_pages_prices_as_hints(): void
    {
        $this->makeChallenge(['points' => 500]);
        $this->makeChallenge(['points' => 3]);

        $html = $this->signIn($this->makePlayer())->get('/user/dashboard')->getContent();
        preg_match('/<input[^>]*id="min"[^>]*>/', $html, $min);
        preg_match('/<input[^>]*id="max"[^>]*>/', $html, $max);

        $this->assertStringContainsString('placeholder="3"', $min[0] ?? '');
        $this->assertStringNotContainsString('value=', $min[0] ?? '');
        $this->assertStringContainsString('placeholder="500"', $max[0] ?? '');
        $this->assertStringNotContainsString('value=', $max[0] ?? '');
    }






    // -----------------------------------------------------------
    // test_the_points_filter_starts_empty_without_challenges
    // -----------------------------------------------------------
    //
    // With no challenge on the page the boxes have no price
    // to show as a hint either.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_points_filter_starts_empty_without_challenges(): void
    {
        $response = $this->signIn($this->makePlayer())->get('/user/dashboard');

        $response->assertSee('id="min" name="min" min="0" placeholder=""', false);
        $response->assertSee('id="max" name="max" min="0" placeholder=""', false);
    }
}
