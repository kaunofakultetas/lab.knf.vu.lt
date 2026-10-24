<?php

// -----------------------------------------------------------
//  [*] VotingTest — thumbs up and down on a challenge
//
//  The vote route counts one thumb per player and challenge
//  in Redis — ThumbsUp_ and ThumbsDown_ keys per challenge,
//  and a Vote_ key per challenge and player, holding the
//  vote's direction, that locks the player's buttons. It is
//  a POST the page's script sends with the CSRF token of
//  the page's csrf-token meta tag, answers with an empty
//  body, and asks for a session but not for a solve: the
//  buttons sit on the challenge page too, before the
//  answer. Only up and down are votes, and only for a
//  challenge the player may open.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature\Play;

use App\Http\Controllers\Users\ChallengesController;
use App\Models\Challenges;
use App\Models\User;
use Illuminate\Redis\Events\CommandExecuted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;








// -----------------------------------------------------------
// VotingTest
// -----------------------------------------------------------
//
//   vote    — casts a vote as the given player
//   counter — reads a vote counter of a challenge
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
    // counter
    // -----------------------------------------------------------
    //
    // The ThumbsUp or ThumbsDown count of the challenge, 0
    // when Redis has none yet.
    //
    // Used by:
    //   - the tests below
    // -----------------------------------------------------------

    private function counter(string $thumbs, Challenges $challenge): int
    {
        return (int) Redis::get("{$thumbs}_{$challenge->public_id}");
    }






    // -----------------------------------------------------------
    // test_a_vote_is_counted_and_answered_with_an_empty_page
    // -----------------------------------------------------------
    //
    // An up vote raises the challenge's up count, marks the
    // player as having voted up, and the route answers 200
    // with nothing in the body.
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
        $this->assertSame(1, $this->counter('ThumbsUp', $challenge));
        $this->assertSame(0, $this->counter('ThumbsDown', $challenge));
        $this->assertSame('up', Redis::get("Vote_{$challenge->public_id}_{$player->id}"));
    }






    // -----------------------------------------------------------
    // test_a_down_vote_is_counted_on_its_own_counter
    // -----------------------------------------------------------
    //
    // A down vote raises the down count only.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_down_vote_is_counted_on_its_own_counter(): void
    {
        $challenge = $this->makeChallenge();

        $this->vote($this->makePlayer(), 'down', $challenge);

        $this->assertSame(0, $this->counter('ThumbsUp', $challenge));
        $this->assertSame(1, $this->counter('ThumbsDown', $challenge));
    }






    // -----------------------------------------------------------
    // test_each_player_votes_once_per_challenge
    // -----------------------------------------------------------
    //
    // A player's second vote on a challenge — the same thumb
    // or the other one — changes nothing.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_each_player_votes_once_per_challenge(): void
    {
        $player = $this->makePlayer();
        $challenge = $this->makeChallenge();

        $this->vote($player, 'up', $challenge);
        $this->vote($player, 'up', $challenge);
        $this->vote($player, 'down', $challenge);

        $this->assertSame(1, $this->counter('ThumbsUp', $challenge));
        $this->assertSame(0, $this->counter('ThumbsDown', $challenge));
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

        $this->assertSame(2, $this->counter('ThumbsUp', $challenge));
        $this->assertSame(1, $this->counter('ThumbsDown', $challenge));
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

        $this->assertSame(1, $this->counter('ThumbsUp', $first));
        $this->assertSame(0, $this->counter('ThumbsDown', $first));
        $this->assertSame(1, $this->counter('ThumbsDown', $second));
    }






    // -----------------------------------------------------------
    // test_an_unknown_vote_type_uses_up_nothing
    // -----------------------------------------------------------
    //
    // A type other than up or down answers 404 and counts
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
        $this->vote($player, 'up', $challenge)->assertOk();

        $this->assertSame(1, $this->counter('ThumbsUp', $challenge));
        $this->assertSame(0, $this->counter('ThumbsDown', $challenge));
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
    // test_simultaneous_votes_are_all_counted
    // -----------------------------------------------------------
    //
    // Two votes at the same moment both count. The counter is
    // raised with INCR, so there is no read to slip the other
    // player's vote in after; should one come back, the other
    // vote lands right after it and must still count.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_simultaneous_votes_are_all_counted(): void
    {
        $challenge = $this->makeChallenge();
        $counter = "ThumbsUp_{$challenge->public_id}";
        $interleaved = false;
        Redis::enableEvents();
        Redis::purge();
        Event::listen(CommandExecuted::class, function (CommandExecuted $event) use ($counter, &$interleaved) {
            if (!$interleaved && strtolower($event->command) === 'get' && ($event->parameters[0] ?? null) === $counter) {
                $interleaved = true;
                Redis::incr($counter);
            }
        });

        $this->vote($this->makePlayer(), 'up', $challenge);

        $this->assertSame(1 + ($interleaved ? 1 : 0), $this->counter('ThumbsUp', $challenge));
    }






    // -----------------------------------------------------------
    // test_a_vote_is_never_a_plain_link
    // -----------------------------------------------------------
    //
    // Opened as a link, which another site can make a
    // signed-in player follow, the vote address answers 405
    // and counts nothing.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_vote_is_never_a_plain_link(): void
    {
        $player = $this->makePlayer();
        $challenge = $this->makeChallenge();

        $this->signIn($player)->get("/user/challenge/vote/up/{$challenge->public_id}")->assertStatus(405);

        $this->assertSame(0, $this->counter('ThumbsUp', $challenge));
        $this->assertNull(Redis::get("Vote_{$challenge->public_id}_{$player->id}"));
    }






    // -----------------------------------------------------------
    // test_a_vote_for_an_unknown_challenge_is_404
    // -----------------------------------------------------------
    //
    // A public id no challenge has answers 404 and leaves no
    // counter or lock behind in Redis.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_vote_for_an_unknown_challenge_is_404(): void
    {
        $this->signIn($this->makePlayer())->post('/user/challenge/vote/up/no-such-challenge')->assertNotFound();

        $this->assertSame([], Redis::keys('*no-such-challenge*'));
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

        $this->assertSame(1, $this->counter('ThumbsUp', $challenge));
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
    // click or a script, counts once: the vote claims the
    // player's mark and raises the counter only when the claim
    // is new. The second vote runs in full right after the
    // first one's first step on the mark, where a check followed
    // by a separate write would let both through.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_simultaneous_votes_of_one_player_count_once(): void
    {
        $challenge = $this->makeChallenge();
        $player = $this->makePlayer();
        $mark = "Vote_{$challenge->public_id}_{$player->id}";
        $interleaved = false;
        Redis::enableEvents();
        Redis::purge();
        Event::listen(CommandExecuted::class, function (CommandExecuted $event) use ($mark, $challenge, &$interleaved) {
            if (!$interleaved && ($event->parameters[0] ?? null) === $mark) {
                $interleaved = true;
                app(ChallengesController::class)->vote(request(), 'up', $challenge->public_id);
            }
        });

        $this->vote($player, 'up', $challenge);

        $this->assertTrue($interleaved);
        $this->assertSame(1, $this->counter('ThumbsUp', $challenge));
    }
}
