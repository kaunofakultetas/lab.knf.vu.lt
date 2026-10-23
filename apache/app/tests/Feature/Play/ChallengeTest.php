<?php

// -----------------------------------------------------------
//  [*] ChallengeTest — opening a challenge and answering it
//
//  The challenge page shows the task, its files and the
//  partner behind it, and takes the answer. A correct answer
//  is booked three ways — a submission row worth the
//  challenge's points, the points added to the player, a
//  history row — and announced in the shared solve history
//  kept in Redis. The answer is compared with the stored
//  flag after stripping the VU{...} wrapper players type
//  around flags from both. A challenge can be solved once:
//  the validate.ifsolved middleware turns a solved challenge
//  away before the page or the answer check runs, and since
//  the race-condition fix a unique key makes the database
//  refuse a second submission row too.
//
//  The dashboard the answers redirect to is DashboardTest's —
//  it may only be rendered once per PHP process.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature\Play;

use App\Models\Challenges;
use App\Models\ChallengesFiles;
use App\Models\ChallengesHistory;
use App\Models\ChallengesSubmitted;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;








// -----------------------------------------------------------
// ChallengeTest
// -----------------------------------------------------------
//
// Every test starts as a freshly signed-in local player;
// the hidden-challenge tests sign in VU accounts too.
//
//   answer — posts an answer from the challenge page
//
// Used by:
//   - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------

final class ChallengeTest extends TestCase
{

    // The signed-in player
    private User $player;






    // -----------------------------------------------------------
    // setUp
    // -----------------------------------------------------------
    //
    // Signs a new player in.
    //
    // Used by:
    //   - PHPUnit, before every test
    // -----------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();
        $this->player = $this->makePlayer();
        $this->signIn($this->player);
    }






    // -----------------------------------------------------------
    // answer
    // -----------------------------------------------------------
    //
    // Posts the answer to the challenge from its page — or
    // from its page inside the group given by public id.
    //
    // Used by:
    //   - the tests below
    // -----------------------------------------------------------

    private function answer(Challenges $challenge, string $answer, ?string $group = null): TestResponse
    {
        $page = "/user/challenge/{$challenge->public_id}" . ($group === null ? '' : "/{$group}");
        return $this->from($page)->post($page, ['answer' => $answer]);
    }






    // -----------------------------------------------------------
    // test_the_page_shows_the_task
    // -----------------------------------------------------------
    //
    // The page carries the name, the points, the task as the
    // admin wrote it — HTML included — the partner credit with
    // its uploaded picture, and the answer form posting back
    // to the page.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_page_shows_the_task(): void
    {
        $partner = $this->makePartner(['name' => 'CERT-LT', 'picture' => 'cert-lt.png']);
        $challenge = $this->makeChallenge(['name' => 'Packet Detective', 'points' => 25, 'partner_id' => $partner->id]);

        $response = $this->get("/user/challenge/{$challenge->public_id}");

        $response->assertOk();
        $response->assertViewIs('users.challenge');
        $response->assertSee('Packet Detective');
        $response->assertSee('25 points');
        $response->assertSee('<b>capture.pcap</b>', false);
        $response->assertSee('Challenge created by CERT-LT');
        $response->assertSee('src="https://lab.knf.vu.lt/upload/cert-lt.png"', false);
        $response->assertSee("action=\"https://lab.knf.vu.lt/user/challenge/{$challenge->public_id}\"", false);
    }






    // -----------------------------------------------------------
    // test_the_answer_never_reaches_the_page
    // -----------------------------------------------------------
    //
    // Nothing on the challenge page gives the stored answer
    // away.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_answer_never_reaches_the_page(): void
    {
        $challenge = $this->makeChallenge(['answer' => 'the_secret_flag_42']);

        $this->get("/user/challenge/{$challenge->public_id}")->assertOk()->assertDontSee('the_secret_flag_42');
    }






    // -----------------------------------------------------------
    // test_attached_files_are_listed_with_their_links
    // -----------------------------------------------------------
    //
    // The challenge's files appear as links under their names;
    // the files of other challenges do not.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_attached_files_are_listed_with_their_links(): void
    {
        $challenge = $this->makeChallenge();
        $other = $this->makeChallenge();
        ChallengesFiles::create(['challenge_id' => $challenge->id, 'name' => 'capture.pcap', 'file_url' => 'https://files.test/capture.pcap']);
        ChallengesFiles::create(['challenge_id' => $challenge->id, 'name' => 'notes.txt', 'file_url' => 'https://files.test/notes.txt']);
        ChallengesFiles::create(['challenge_id' => $other->id, 'name' => 'other.zip', 'file_url' => 'https://files.test/other.zip']);

        $response = $this->get("/user/challenge/{$challenge->public_id}");

        $response->assertSee('Related files');
        $response->assertSee('href="https://files.test/capture.pcap"', false);
        $response->assertSee('href="https://files.test/notes.txt"', false);
        $response->assertDontSee('other.zip');
    }






    // -----------------------------------------------------------
    // test_a_challenge_without_files_has_no_file_list
    // -----------------------------------------------------------
    //
    // The files card is left out when there is nothing in it.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_challenge_without_files_has_no_file_list(): void
    {
        $challenge = $this->makeChallenge();

        $this->get("/user/challenge/{$challenge->public_id}")->assertOk()->assertDontSee('Related files');
    }






    // -----------------------------------------------------------
    // test_an_unknown_challenge_leads_back_to_the_dashboard
    // -----------------------------------------------------------
    //
    // A public id that names no challenge sends the player to
    // the dashboard with an error, for the page and for an
    // answer alike.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_unknown_challenge_leads_back_to_the_dashboard(): void
    {
        $missing = (string) Str::uuid();

        $this->get("/user/challenge/{$missing}")->assertRedirect('/user/dashboard');
        $this->assertErrorFlashed("Challenge doesn't exists!");

        $this->post("/user/challenge/{$missing}", ['answer' => 'anything'])->assertRedirect('/user/dashboard');
        $this->assertErrorFlashed("Challenge doesn't exists!");
    }






    // -----------------------------------------------------------
    // test_a_solved_challenge_cannot_be_opened_again
    // -----------------------------------------------------------
    //
    // Once solved, the page sends the player to the dashboard
    // with a message instead.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_solved_challenge_cannot_be_opened_again(): void
    {
        $challenge = $this->makeChallenge();
        $this->recordSolve($this->player, $challenge);

        $this->get("/user/challenge/{$challenge->public_id}")->assertRedirect('/user/dashboard');

        $this->assertErrorFlashed('You already solved this challenge!');
    }






    // -----------------------------------------------------------
    // test_a_challenge_solved_by_someone_else_stays_open
    // -----------------------------------------------------------
    //
    // Solves are per player: another player's solve closes
    // nothing for this one.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_challenge_solved_by_someone_else_stays_open(): void
    {
        $challenge = $this->makeChallenge();
        $this->recordSolve($this->makePlayer(), $challenge);

        $this->get("/user/challenge/{$challenge->public_id}")->assertOk();
    }






    // -----------------------------------------------------------
    // test_a_group_page_needs_an_existing_group
    // -----------------------------------------------------------
    //
    // Opened through a group id that names no group, the page
    // goes back where the player came from with an error.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_group_page_needs_an_existing_group(): void
    {
        $challenge = $this->makeChallenge();

        $this->from('/user/leaderboard')
            ->get("/user/challenge/{$challenge->public_id}/" . Str::uuid())
            ->assertRedirect('/user/leaderboard');

        $this->assertErrorFlashed('Error!');
    }






    // -----------------------------------------------------------
    // test_a_group_page_posts_its_answer_inside_the_group
    // -----------------------------------------------------------
    //
    // Opened through an existing group, the page renders and
    // its form posts back to the group's address.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_group_page_posts_its_answer_inside_the_group(): void
    {
        $challenge = $this->makeChallenge();
        $group = $this->makeGroup();

        $response = $this->get("/user/challenge/{$challenge->public_id}/{$group->public_id}");

        $response->assertOk();
        $response->assertSee("action=\"https://lab.knf.vu.lt/user/challenge/{$challenge->public_id}/{$group->public_id}\"", false);
    }






    // -----------------------------------------------------------
    // test_a_correct_answer_books_the_solve
    // -----------------------------------------------------------
    //
    // The right answer leads to the dashboard with the points
    // in the message, and books a submission worth the
    // challenge's points, the points on the account and a
    // history row.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_correct_answer_books_the_solve(): void
    {
        $challenge = $this->makeChallenge(['points' => 25]);

        $response = $this->answer($challenge, $challenge->answer);

        $response->assertRedirect('/user/dashboard');
        $response->assertSessionHas('success', 'Your answer is correct! You received 25 points');
        $submission = ChallengesSubmitted::where(['user_id' => $this->player->id, 'challenge_id' => $challenge->id])->firstOrFail();
        $this->assertSame(25, $submission->points_given);
        $this->assertEqualsWithDelta(time(), $submission->created->getTimestamp(), 60);
        $this->assertSame(25, $this->player->refresh()->points);
        $this->assertSame(1, ChallengesHistory::where(['user_id' => $this->player->id, 'challenge_id' => $challenge->id])->count());
    }






    // -----------------------------------------------------------
    // test_a_solve_records_the_client_address
    // -----------------------------------------------------------
    //
    // The submission keeps the address the player answered
    // from, as getUserIP picks it.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_solve_records_the_client_address(): void
    {
        $challenge = $this->makeChallenge();
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.8';

        $this->answer($challenge, $challenge->answer);

        $this->assertSame('198.51.100.8', ChallengesSubmitted::where('challenge_id', $challenge->id)->value('ip'));
    }






    // -----------------------------------------------------------
    // test_the_answer_may_be_typed_in_the_flag_format
    // -----------------------------------------------------------
    //
    // A flag stored bare matches the answer typed as VU{...}:
    // the wrapper is stripped before comparing, and so are
    // the blanks around the answer.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_answer_may_be_typed_in_the_flag_format(): void
    {
        $wrapped = $this->makeChallenge(['answer' => 'packet_wizard']);
        $spaced = $this->makeChallenge(['answer' => 'stego_master']);

        $this->answer($wrapped, 'VU{packet_wizard}')->assertSessionHas('success');
        $this->answer($spaced, '   stego_master   ')->assertSessionHas('success');

        $this->assertSame(20, $this->player->refresh()->points);
    }






    // -----------------------------------------------------------
    // test_answers_are_case_sensitive
    // -----------------------------------------------------------
    //
    // Letter case counts: the flag in capitals is a wrong
    // answer.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_answers_are_case_sensitive(): void
    {
        $challenge = $this->makeChallenge(['answer' => 'packet_wizard']);

        $this->answer($challenge, 'VU{PACKET_WIZARD}');

        $this->assertErrorFlashed('Wrong answer');
        $this->assertSame(0, $this->player->refresh()->points);
    }






    // -----------------------------------------------------------
    // test_a_wrong_answer_books_nothing
    // -----------------------------------------------------------
    //
    // A wrong answer goes back to the challenge page with an
    // error, and books neither a submission, nor points, nor
    // history — the challenge stays open.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_wrong_answer_books_nothing(): void
    {
        $challenge = $this->makeChallenge();

        $response = $this->answer($challenge, 'not-the-flag');

        $response->assertRedirect("/user/challenge/{$challenge->public_id}");
        $this->assertErrorFlashed('Wrong answer');
        $this->assertSame(0, ChallengesSubmitted::where('challenge_id', $challenge->id)->count());
        $this->assertSame(0, ChallengesHistory::where('challenge_id', $challenge->id)->count());
        $this->assertSame(0, $this->player->refresh()->points);
        $this->get("/user/challenge/{$challenge->public_id}")->assertOk();
    }






    // -----------------------------------------------------------
    // test_a_solved_challenge_pays_only_once
    // -----------------------------------------------------------
    //
    // Answering a solved challenge again is turned away before
    // the answer is checked: no second submission, no second
    // payment.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_solved_challenge_pays_only_once(): void
    {
        $challenge = $this->makeChallenge();
        $this->answer($challenge, $challenge->answer)->assertSessionHas('success');

        $this->answer($challenge, $challenge->answer)->assertRedirect('/user/dashboard');

        $this->assertErrorFlashed('You already solved this challenge!');
        $this->assertSame(10, $this->player->refresh()->points);
        $this->assertSame(1, ChallengesSubmitted::where('challenge_id', $challenge->id)->count());
    }






    // -----------------------------------------------------------
    // test_the_database_refuses_a_second_solve_row
    // -----------------------------------------------------------
    //
    // The unique key on (user_id, challenge_id) stops a second
    // submission row even when two answers race past the
    // middleware at once.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_database_refuses_a_second_solve_row(): void
    {
        $challenge = $this->makeChallenge();
        $this->recordSolve($this->player, $challenge);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('user_challenge_unique');

        ChallengesSubmitted::create([
            'user_id'      => $this->player->id,
            'challenge_id' => $challenge->id,
            'points_given' => $challenge->points,
            'created'      => date('Y-m-d H:i:s'),
            'ip'           => '127.0.0.1',
        ]);
    }






    // -----------------------------------------------------------
    // test_points_add_up_over_challenges
    // -----------------------------------------------------------
    //
    // Each solve adds its own challenge's points to the total.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_points_add_up_over_challenges(): void
    {
        $easy = $this->makeChallenge(['points' => 10]);
        $hard = $this->makeChallenge(['points' => 250]);

        $this->answer($easy, $easy->answer);
        $this->answer($hard, $hard->answer);

        $this->assertSame(260, $this->player->refresh()->points);
    }






    // -----------------------------------------------------------
    // test_solving_inside_a_group_returns_to_the_group
    // -----------------------------------------------------------
    //
    // An answer given on a group's challenge page leads back
    // to that group's dashboard.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_solving_inside_a_group_returns_to_the_group(): void
    {
        $challenge = $this->makeChallenge(['points' => 15]);
        $group = $this->makeGroup();

        $response = $this->answer($challenge, $challenge->answer, $group->public_id);

        $response->assertRedirect("/user/group/{$group->public_id}");
        $response->assertSessionHas('success', 'Your answer is correct! You received 15 points');
    }






    // -----------------------------------------------------------
    // test_a_hidden_challenge_is_playable_from_its_group
    // -----------------------------------------------------------
    //
    // Hidden challenges — what lab admins create — stay off
    // the dashboard but are played by the members of the
    // groups they are assigned to: the page opens and the
    // answer pays.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_hidden_challenge_is_playable_from_its_group(): void
    {
        $challenge = $this->makeChallenge(['hidden' => 1]);
        $group = $this->makeGroup();
        $this->assignToGroup($group, $challenge);
        $member = $this->makeSsoPlayer();
        $this->joinGroup($group, $member);
        $this->signIn($member);

        $this->get("/user/challenge/{$challenge->public_id}/{$group->public_id}")->assertOk();
        $this->answer($challenge, $challenge->answer, $group->public_id)->assertSessionHas('success');

        $this->assertSame(10, $member->refresh()->points);
    }






    // -----------------------------------------------------------
    // test_a_hidden_challenge_stays_with_its_groups
    // -----------------------------------------------------------
    //
    // To a player outside its groups — a local account, or a
    // member of another group — a hidden challenge answers as
    // an unknown one, with or without its group in the
    // address, and its answer scores nothing.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_hidden_challenge_stays_with_its_groups(): void
    {
        $group = $this->makeGroup();
        $challenge = $this->makeChallenge(['hidden' => 1, 'answer' => 'group_only_flag', 'content' => '<p>For the group only</p>']);
        $this->assignToGroup($group, $challenge);
        $elsewhere = $this->makeSsoPlayer();
        $this->joinGroup($this->makeGroup(), $elsewhere);

        foreach ([$this->player, $elsewhere] as $outsider) {
            $this->signIn($outsider);
            $this->get("/user/challenge/{$challenge->public_id}")->assertRedirect('/user/dashboard');
            $this->assertErrorFlashed("Challenge doesn't exists!");
            $this->get("/user/challenge/{$challenge->public_id}/{$group->public_id}")->assertRedirect('/user/dashboard');
            $this->answer($challenge, 'group_only_flag', $group->public_id)->assertRedirect('/user/dashboard');
            $this->assertSame(0, $outsider->refresh()->points);
        }
        $this->assertSame(0, ChallengesSubmitted::where('challenge_id', $challenge->id)->count());
    }






    // -----------------------------------------------------------
    // test_a_solve_is_announced_in_the_shared_history
    // -----------------------------------------------------------
    //
    // The solve history every player sees in the sidebar is
    // rewritten in Redis on each solve, newest first, naming
    // the player and the challenge.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_solve_is_announced_in_the_shared_history(): void
    {
        $challenge = $this->makeChallenge(['name' => 'Packet Detective']);

        $this->answer($challenge, $challenge->answer);

        $history = json_decode(Redis::get('tasks_history'), true);
        $this->assertSame($this->player->username, $history[0]['username']);
        $this->assertSame('Packet Detective', $history[0]['name']);
        $this->assertSame($this->player->id, $history[0]['user_id']);
    }






    // -----------------------------------------------------------
    // test_the_shared_history_keeps_the_six_newest_solves
    // -----------------------------------------------------------
    //
    // However many solves there are, the history holds the six
    // newest, newest first.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_shared_history_keeps_the_six_newest_solves(): void
    {
        $names = [];
        foreach (range(1, 7) as $n) {
            $challenge = $this->makeChallenge(['name' => "Challenge number {$n}"]);
            $this->answer($challenge, $challenge->answer);
            $names[] = $challenge->name;
        }

        $history = json_decode(Redis::get('tasks_history'), true);
        $this->assertSame(array_reverse(array_slice($names, 1)), array_column($history, 'name'));
    }






    // -----------------------------------------------------------
    // test_a_numeric_flag_matches_only_as_written
    // -----------------------------------------------------------
    //
    // A flag that is a number is matched as text: another
    // spelling of the same number is a wrong answer, the flag as
    // written is right.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_numeric_flag_matches_only_as_written(): void
    {
        $challenge = $this->makeChallenge(['answer' => '100', 'points' => 10]);

        foreach (['1e2', '100.0', '0100'] as $spelling) {
            $this->answer($challenge, $spelling)->assertSessionHasErrors();
        }
        $this->assertSame(0, $this->player->refresh()->points);

        $this->answer($challenge, '100');
        $this->assertSame(10, $this->player->refresh()->points);
    }






    // -----------------------------------------------------------
    // test_simultaneous_solves_keep_all_points
    // -----------------------------------------------------------
    //
    // Two right answers at the same moment, from two tabs, both
    // count. The other tab's 25-point solve is slipped in where a
    // read-add-write of the points would lose it — right after
    // this request first reads the account once its own solve is
    // booked; the points are added in SQL, so all of them stay.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_simultaneous_solves_keep_all_points(): void
    {
        $challenge = $this->makeChallenge(['points' => 10]);
        $booked = false;
        $interleaved = false;
        DB::listen(function (QueryExecuted $query) use (&$booked, &$interleaved) {
            if (str_starts_with($query->sql, 'insert into `cyber_challenges_submitted`')) {
                $booked = true;
            } elseif ($booked && !$interleaved && preg_match('/^select .* from `cyber_users`/', $query->sql)) {
                $interleaved = true;
                DB::table('cyber_users')->where('id', $this->player->id)->increment('points', 25);
            }
        });

        $this->answer($challenge, $challenge->answer);

        $this->assertSame(10 + ($interleaved ? 25 : 0), $this->player->refresh()->points);
    }






    // -----------------------------------------------------------
    // test_a_double_submitted_answer_is_refused_cleanly
    // -----------------------------------------------------------
    //
    // The same right answer sent twice at once — a double click —
    // passes both "already solved" checks; the other request's
    // solve is booked right after this one's last check, the
    // table's unique key refuses this one, and it goes back as an
    // already solved challenge, not to the error page.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_double_submitted_answer_is_refused_cleanly(): void
    {
        $challenge = $this->makeChallenge();
        $checks = 0;
        DB::listen(function (QueryExecuted $query) use (&$checks, $challenge) {
            if (str_starts_with($query->sql, 'select count(*) as aggregate from `cyber_challenges_submitted`') && ++$checks === 2) {
                DB::table('cyber_challenges_submitted')->insert([
                    'user_id'      => $this->player->id,
                    'challenge_id' => $challenge->id,
                    'points_given' => $challenge->points,
                    'created'      => date('Y-m-d H:i:s'),
                    'ip'           => '127.0.0.1',
                ]);
            }
        });

        $response = $this->answer($challenge, $challenge->answer);

        $response->assertRedirect("/user/challenge/{$challenge->public_id}");
        $response->assertSessionHasErrors();
        $this->assertSame(1, ChallengesSubmitted::where('user_id', $this->player->id)->count());
    }






    // -----------------------------------------------------------
    // test_a_flag_saved_with_its_wrapper_is_solvable
    // -----------------------------------------------------------
    //
    // A flag saved with its VU{…} wrapper matches the answer
    // given with the wrapper or without it.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_flag_saved_with_its_wrapper_is_solvable(): void
    {
        $wrapped = $this->makeChallenge(['answer' => 'VU{wrapped_flag}', 'points' => 10]);
        $other = $this->makeChallenge(['answer' => 'VU{other_flag}', 'points' => 20]);

        $this->answer($wrapped, 'VU{wrapped_flag}')->assertSessionHas('success');
        $this->answer($other, 'other_flag')->assertSessionHas('success');

        $this->assertSame(30, $this->player->refresh()->points);
    }






    // -----------------------------------------------------------
    // test_a_flag_with_a_brace_inside_is_solvable
    // -----------------------------------------------------------
    //
    // A flag with a closing brace inside matches when typed as
    // it was saved.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_flag_with_a_brace_inside_is_solvable(): void
    {
        $challenge = $this->makeChallenge(['answer' => 'brace}inside', 'points' => 10]);

        $this->answer($challenge, 'brace}inside')->assertSessionHas('success');

        $this->assertSame(10, $this->player->refresh()->points);
    }






    // -----------------------------------------------------------
    // test_an_empty_answer_never_solves
    // -----------------------------------------------------------
    //
    // An answer that is empty once the wrapper is stripped is
    // wrong, even against a flag saved as a bare wrapper.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_empty_answer_never_solves(): void
    {
        $challenge = $this->makeChallenge(['answer' => 'VU{}']);

        $this->answer($challenge, '');
        $this->assertErrorFlashed('Wrong answer');
        $this->answer($challenge, 'VU{}');
        $this->assertErrorFlashed('Wrong answer');

        $this->assertSame(0, $this->player->refresh()->points);
    }
}
