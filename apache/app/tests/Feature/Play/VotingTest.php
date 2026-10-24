<?php

// -----------------------------------------------------------
//  [*] VotingTest — thumbs up and down on a challenge
//
//  The vote route keeps one thumb per player and challenge
//  as a row of cyber_challenges_votes holding up or down;
//  the pages count the rows, and the player's own row locks
//  the player's buttons. It is a POST the page's script
//  sends with the CSRF token of the page's csrf-token meta
//  tag, answers with an empty body, and asks for a session
//  but not for a solve: the buttons sit on the challenge
//  page too, before the answer. Only up and down are votes,
//  and only for a challenge the player may open.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature\Play;

use App\Http\Controllers\Users\ChallengesController;
use App\Models\Challenges;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;








// -----------------------------------------------------------
// VotingTest
// -----------------------------------------------------------
//
//   vote   — casts a vote as the given player
//   votes  — counts a challenge's rows of one vote
//   voteOf — reads a player's row on a challenge
//
// Used by:
//   - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------

final class VotingTest extends TestCase
{






    // -----------------------------------------------------------
    // vote
    // -----------------------------------------------------------
    //
    // Signs the player in and posts to the vote route with
    // the vote type given — up, down or anything else.
    //
    // Used by:
    //   - the tests below
    // -----------------------------------------------------------

    private function vote(User $player, string $type, Challenges $challenge): TestResponse
    {
        return $this->signIn($player)->post("/user/challenge/vote/{$type}/{$challenge->public_id}");
    }






    // -----------------------------------------------------------
    // votes
    // -----------------------------------------------------------
    //
    // How many of the challenge's rows in
    // cyber_challenges_votes hold the vote given, up or down.
    //
    // Used by:
    //   - the tests below
    // -----------------------------------------------------------

    private function votes(string $vote, Challenges $challenge): int
    {
        return DB::table('cyber_challenges_votes')->where(['challenge_id' => $challenge->id, 'vote' => $vote])->count();
    }






    // -----------------------------------------------------------
    // voteOf
    // -----------------------------------------------------------
    //
    // The vote of the player's row on the challenge, up or
    // down, or nothing when the player has none.
    //
    // Used by:
    //   - the tests below
    // -----------------------------------------------------------

    private function voteOf(User $player, Challenges $challenge): ?string
    {
        return DB::table('cyber_challenges_votes')->where(['user_id' => $player->id, 'challenge_id' => $challenge->id])->value('vote');
    }






    // -----------------------------------------------------------
    // test_a_vote_is_counted_and_answered_with_an_empty_page
    // -----------------------------------------------------------
    //
    // An up vote becomes the player's row on the challenge,
    // holding up and the time it was cast, and the route
    // answers 200 with nothing in the body.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_vote_is_counted_and_answered_with_an_empty_page(): void
    {
        $player = $this->makePlayer();
        $challenge = $this->makeChallenge();

        $response = $this->vote($player, 'up', $challenge);

        $response->assertOk();
        $this->assertSame('', $response->getContent());
        $this->assertSame(1, $this->votes('up', $challenge));
        $this->assertSame(0, $this->votes('down', $challenge));
        $this->assertSame('up', $this->voteOf($player, $challenge));
        $this->assertNotNull(DB::table('cyber_challenges_votes')->where(['user_id' => $player->id])->value('created'));
    }






    // -----------------------------------------------------------
    // test_a_down_vote_counts_as_down_only
    // -----------------------------------------------------------
    //
    // A down vote is kept as down and raises the down count
    // only.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_down_vote_counts_as_down_only(): void
    {
        $challenge = $this->makeChallenge();

        $this->vote($this->makePlayer(), 'down', $challenge);

        $this->assertSame(0, $this->votes('up', $challenge));
        $this->assertSame(1, $this->votes('down', $challenge));
    }






    // -----------------------------------------------------------
    // test_each_player_votes_once_per_challenge
    // -----------------------------------------------------------
    //
    // A player's second vote on a challenge — the same thumb
    // or the other one — changes nothing: the first vote's
    // row stays as it was, and no second row is added.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_each_player_votes_once_per_challenge(): void
    {
        $player = $this->makePlayer();
        $challenge = $this->makeChallenge();

        $this->vote($player, 'up', $challenge);
        $this->vote($player, 'up', $challenge)->assertOk();
        $this->vote($player, 'down', $challenge)->assertOk();

        $this->assertSame(1, $this->votes('up', $challenge));
        $this->assertSame(0, $this->votes('down', $challenge));
        $this->assertSame('up', $this->voteOf($player, $challenge));
    }






    // -----------------------------------------------------------
    // test_the_votes_of_different_players_add_up
    // -----------------------------------------------------------
    //
    // Every player has a vote of their own.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_votes_of_different_players_add_up(): void
    {
        $challenge = $this->makeChallenge();

        $this->vote($this->makePlayer(), 'up', $challenge);
        $this->vote($this->makePlayer(), 'up', $challenge);
        $this->vote($this->makePlayer(), 'down', $challenge);

        $this->assertSame(2, $this->votes('up', $challenge));
        $this->assertSame(1, $this->votes('down', $challenge));
    }






    // -----------------------------------------------------------
    // test_votes_are_kept_apart_per_challenge
    // -----------------------------------------------------------
    //
    // Voting on one challenge leaves the player free to vote
    // on another, and leaves the other's counts alone.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_votes_are_kept_apart_per_challenge(): void
    {
        $player = $this->makePlayer();
        $first = $this->makeChallenge();
        $second = $this->makeChallenge();

        $this->vote($player, 'up', $first);
        $this->vote($player, 'down', $second);

        $this->assertSame(1, $this->votes('up', $first));
        $this->assertSame(0, $this->votes('down', $first));
        $this->assertSame(1, $this->votes('down', $second));
    }






    // -----------------------------------------------------------
    // test_an_unknown_vote_type_uses_up_nothing
    // -----------------------------------------------------------
    //
    // A type other than up or down answers 404 and keeps
    // nothing, and the player's real vote still counts after
    // it.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_unknown_vote_type_uses_up_nothing(): void
    {
        $player = $this->makePlayer();
        $challenge = $this->makeChallenge();

        $this->vote($player, 'sideways', $challenge)->assertNotFound();
        $this->assertNull($this->voteOf($player, $challenge));
        $this->vote($player, 'up', $challenge)->assertOk();

        $this->assertSame(1, $this->votes('up', $challenge));
        $this->assertSame(0, $this->votes('down', $challenge));
    }






    // -----------------------------------------------------------
    // test_the_challenge_page_shows_the_counts_and_locks_after_a_vote
    // -----------------------------------------------------------
    //
    // The challenge page — open before the answer — shows the
    // current counts, with live buttons for a player who has
    // not voted and disabled ones for a player who has.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_challenge_page_shows_the_counts_and_locks_after_a_vote(): void
    {
        $challenge = $this->makeChallenge();
        $voter = $this->makePlayer();
        $this->vote($this->makePlayer(), 'up', $challenge);
        $this->vote($this->makePlayer(), 'up', $challenge);

        $this->signIn($voter)->get("/user/challenge/{$challenge->public_id}")
            ->assertSee('<span id="thumbs-up-count">2</span>', false)
            ->assertSee('<span id="thumbs-down-count">0</span>', false)
            ->assertSee('<button  class="badge border-0 text-white bg-primary thumbs-up"', false);

        $this->vote($voter, 'down', $challenge);
        $this->get("/user/challenge/{$challenge->public_id}")
            ->assertSee('<span id="thumbs-down-count">1</span>', false)
            ->assertSee('<button disabled class="badge border-0 text-white bg-primary thumbs-up"', false);
    }






    // -----------------------------------------------------------
    // test_the_challenge_page_counts_the_rows_of_the_table
    // -----------------------------------------------------------
    //
    // The counts and the lock of the challenge page come from
    // the rows of cyber_challenges_votes alone: rows written
    // straight into the table show as they are, those of
    // another challenge stay out, and the player's own row
    // locks the buttons.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_challenge_page_counts_the_rows_of_the_table(): void
    {
        $challenge = $this->makeChallenge();
        $other = $this->makeChallenge();
        $viewer = $this->makePlayer();
        $rows = [];
        foreach (['up', 'up', 'up', 'down'] as $vote) {
            $rows[] = ['user_id' => $this->makePlayer()->id, 'challenge_id' => $challenge->id, 'vote' => $vote, 'created' => date('Y-m-d H:i:s')];
        }
        $rows[] = ['user_id' => $viewer->id, 'challenge_id' => $challenge->id, 'vote' => 'down', 'created' => date('Y-m-d H:i:s')];
        $rows[] = ['user_id' => $viewer->id, 'challenge_id' => $other->id, 'vote' => 'up', 'created' => date('Y-m-d H:i:s')];
        DB::table('cyber_challenges_votes')->insert($rows);

        $this->signIn($viewer)->get("/user/challenge/{$challenge->public_id}")
            ->assertSee('<span id="thumbs-up-count">3</span>', false)
            ->assertSee('<span id="thumbs-down-count">2</span>', false)
            ->assertSee('<button disabled class="badge border-0 text-white bg-primary thumbs-up"', false);
    }






    // -----------------------------------------------------------
    // test_votes_outlive_an_emptied_redis
    // -----------------------------------------------------------
    //
    // A vote outlives Redis, which a stack update starts empty
    // (vuknflab-redis has no volume): after Redis is emptied
    // the challenge page still counts the vote and keeps the
    // voter's buttons locked.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_votes_outlive_an_emptied_redis(): void
    {
        $challenge = $this->makeChallenge();
        $this->signIn($this->makePlayer())->post("/user/challenge/vote/up/{$challenge->public_id}");
        Redis::connection()->flushdb();

        $response = $this->get("/user/challenge/{$challenge->public_id}");

        $response->assertSee('id="thumbs-up-count">1<', false);
        $response->assertSee('disabled class="badge border-0 text-white bg-primary thumbs-up"', false);
    }






    // -----------------------------------------------------------
    // test_simultaneous_votes_are_all_counted
    // -----------------------------------------------------------
    //
    // Two players' votes at the same moment both count. Each
    // vote is a row of its own, and a page counts the rows
    // when it is drawn, so no total is read and written back
    // where one vote could overwrite the other. The second
    // vote runs in full right after the first one's first step
    // on the table, and the page shows both.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_simultaneous_votes_are_all_counted(): void
    {
        $challenge = $this->makeChallenge();
        $player = $this->makePlayer();
        $other = $this->makePlayer();
        $interleaved = false;
        DB::listen(function (QueryExecuted $query) use ($challenge, $player, $other, &$interleaved) {
            if (!$interleaved && str_contains($query->sql, 'cyber_challenges_votes')) {
                $interleaved = true;
                Auth::guard('users')->setUser($other);
                app(ChallengesController::class)->vote(request(), 'up', $challenge->public_id);
                Auth::guard('users')->setUser($player);
            }
        });

        $this->vote($player, 'up', $challenge)->assertOk();

        $this->assertTrue($interleaved);
        $this->assertSame(2, $this->votes('up', $challenge));
        $this->get("/user/challenge/{$challenge->public_id}")->assertSee('<span id="thumbs-up-count">2</span>', false);
    }






    // -----------------------------------------------------------
    // test_a_vote_is_never_a_plain_link
    // -----------------------------------------------------------
    //
    // Opened as a link, which another site can make a
    // signed-in player follow, the vote address answers 405
    // and keeps nothing.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_vote_is_never_a_plain_link(): void
    {
        $player = $this->makePlayer();
        $challenge = $this->makeChallenge();

        $this->signIn($player)->get("/user/challenge/vote/up/{$challenge->public_id}")->assertStatus(405);

        $this->assertSame(0, $this->votes('up', $challenge));
        $this->assertNull($this->voteOf($player, $challenge));
    }






    // -----------------------------------------------------------
    // test_a_vote_for_an_unknown_challenge_is_404
    // -----------------------------------------------------------
    //
    // A public id no challenge has answers 404 and leaves no
    // row behind.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_vote_for_an_unknown_challenge_is_404(): void
    {
        $this->signIn($this->makePlayer())->post('/user/challenge/vote/up/no-such-challenge')->assertNotFound();

        $this->assertSame(0, DB::table('cyber_challenges_votes')->count());
    }






    // -----------------------------------------------------------
    // test_a_hidden_challenge_takes_votes_from_its_groups_only
    // -----------------------------------------------------------
    //
    // A hidden challenge counts the votes of the members of
    // its groups; anyone else gets 404 and counts nothing, as
    // for its page.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_hidden_challenge_takes_votes_from_its_groups_only(): void
    {
        $challenge = $this->makeChallenge(['hidden' => 1]);
        $group = $this->makeGroup();
        $this->assignToGroup($group, $challenge);
        $member = $this->makeSsoPlayer();
        $this->joinGroup($group, $member);

        $this->vote($this->makePlayer(), 'up', $challenge)->assertNotFound();
        $this->vote($member, 'up', $challenge)->assertOk();

        $this->assertSame(1, $this->votes('up', $challenge));
        $this->assertSame('up', $this->voteOf($member, $challenge));
    }






    // -----------------------------------------------------------
    // test_the_challenge_page_carries_the_csrf_token
    // -----------------------------------------------------------
    //
    // The page whose buttons vote carries the session's CSRF
    // token in a csrf-token meta tag, which the vote script of
    // custom.js sends with the vote.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_challenge_page_carries_the_csrf_token(): void
    {
        $challenge = $this->makeChallenge();

        $page = $this->signIn($this->makePlayer())->get("/user/challenge/{$challenge->public_id}");

        $page->assertSee('<meta name="csrf-token" content="' . csrf_token() . '">', false);
    }






    // -----------------------------------------------------------
    // test_simultaneous_votes_of_one_player_count_once
    // -----------------------------------------------------------
    //
    // A player's vote sent twice at the same moment, by a double
    // click or a script, counts once and answers no error: the
    // table's unique key on the player and the challenge
    // leaves the second row out. The second vote runs in full
    // right after the first one's first step on the table,
    // where a check followed by a separate write would let
    // both through, or fail the first on the key.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_simultaneous_votes_of_one_player_count_once(): void
    {
        $challenge = $this->makeChallenge();
        $player = $this->makePlayer();
        $interleaved = false;
        DB::listen(function (QueryExecuted $query) use ($challenge, &$interleaved) {
            if (!$interleaved && str_contains($query->sql, 'cyber_challenges_votes')) {
                $interleaved = true;
                app(ChallengesController::class)->vote(request(), 'up', $challenge->public_id);
            }
        });

        $this->vote($player, 'up', $challenge)->assertOk();

        $this->assertTrue($interleaved);
        $this->assertSame(1, $this->votes('up', $challenge));
    }






    // -----------------------------------------------------------
    // test_the_votes_of_a_deleted_account_do_not_count
    // -----------------------------------------------------------
    //
    // A vote whose account no longer exists — one cast while
    // the account was being deleted, written after the
    // account's votes were deleted — is not counted.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_votes_of_a_deleted_account_do_not_count(): void
    {
        $challenge = $this->makeChallenge();
        $gone = (int) User::max('id') + 1000;
        $voter = $this->makePlayer();
        DB::table('cyber_challenges_votes')->insert([
            ['user_id' => $voter->id, 'challenge_id' => $challenge->id, 'vote' => 'up', 'created' => date('Y-m-d H:i:s')],
            ['user_id' => $gone, 'challenge_id' => $challenge->id, 'vote' => 'up', 'created' => date('Y-m-d H:i:s')],
            ['user_id' => $gone + 1, 'challenge_id' => $challenge->id, 'vote' => 'down', 'created' => date('Y-m-d H:i:s')],
        ]);

        $this->signIn($this->makePlayer())->get("/user/challenge/{$challenge->public_id}")
            ->assertSee('<span id="thumbs-up-count">1</span>', false)
            ->assertSee('<span id="thumbs-down-count">0</span>', false);
    }
}
