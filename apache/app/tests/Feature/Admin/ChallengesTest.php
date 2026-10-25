<?php

// -----------------------------------------------------------
//  [*] ChallengesTest — challenges in the control panel
//
//  Admins list, search, create, edit and delete challenges.
//  A main admin's new challenge is visible at once; a lab
//  admin's starts hidden — kept off the lab-wide dashboard
//  and played from the lab admin's groups — and only a main
//  admin may switch visibility. A lab admin may open, change
//  and delete only the challenges it created: the r_exists
//  middleware sends it back with an error otherwise, on the
//  pages that name the challenge in the address and on the
//  forms that post its id. Deleting a challenge deletes the
//  files, categories, history, submissions and group places
//  hanging off it and takes the points it gave off its
//  solvers' totals, while changing its points leaves earlier
//  solves as they were. The challenge files have a test
//  class of their own, ChallengeFilesTest.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature\Admin;

use App\Http\Controllers\Admins\ChallengesController;
use App\Models\Admin;
use App\Models\Challenges;
use App\Models\ChallengesCategories;
use App\Models\ChallengesFiles;
use App\Models\ChallengesHistory;
use App\Models\ChallengesSubmitted;
use App\Models\GroupsChallenges;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;








// -----------------------------------------------------------
// ChallengesTest
// -----------------------------------------------------------
//
//   challengeForm — the fields of a valid challenge form
//
// Used by:
//   - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------

final class ChallengesTest extends TestCase
{

    // The refusal the r_exists and perms middleware flash
    private const REFUSAL = "You don't have permissions to do this!";






    // -----------------------------------------------------------
    // challengeForm
    // -----------------------------------------------------------
    //
    // The fields of the create and edit forms with valid
    // values for a new partner; the fields given replace
    // them.
    //
    // Used by:
    //   - the create and update tests below
    // -----------------------------------------------------------

    private function challengeForm(array $fields = []): array
    {
        return array_merge([
            'name'       => 'Packet Detective',
            'partner_id' => $this->makePartner()->id,
            'descr'      => 'Find the flag in the capture.',
            'content'    => '<p>Download <b>capture.pcap</b> and look closer.</p>',
            'answer'     => 'packet_wizard',
            'points'     => 30,
        ], $fields);
    }






    // -----------------------------------------------------------
    // test_the_list_shows_challenges_newest_first
    // -----------------------------------------------------------
    //
    // The list opens with the challenge created last.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_list_shows_challenges_newest_first(): void
    {
        $this->makeChallenge(['name' => 'First Made']);
        $this->makeChallenge(['name' => 'Second Made']);
        $this->makeChallenge(['name' => 'Third Made']);

        $response = $this->signInAdmin($this->makeAdmin(true))->get('/control-panel/challenges');

        $response->assertOk();
        $response->assertViewIs('admins.challenges');
        $response->assertSeeInOrder(['<td>Third Made</td>', '<td>Second Made</td>', '<td>First Made</td>'], false);
    }






    // -----------------------------------------------------------
    // test_the_list_searches_names_descriptions_and_content
    // -----------------------------------------------------------
    //
    // The search term may match the name, the description or
    // the task text; challenges matching none are left out,
    // and a search matching nothing says so.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_list_searches_names_descriptions_and_content(): void
    {
        $this->makeChallenge(['name' => 'Needle In Name']);
        $this->makeChallenge(['name' => 'By Description', 'descr' => 'a haystack with a needle']);
        $this->makeChallenge(['name' => 'By Content', 'content' => '<p>the needle is here</p>']);
        $this->makeChallenge(['name' => 'Unrelated Task']);
        $this->signInAdmin($this->makeAdmin(true));

        $response = $this->get('/control-panel/challenges?q=needle');

        $response->assertSee('<td>Needle In Name</td>', false);
        $response->assertSee('<td>By Description</td>', false);
        $response->assertSee('<td>By Content</td>', false);
        $response->assertDontSee('<td>Unrelated Task</td>', false);
        $this->get('/control-panel/challenges?q=nothing-matches-this')->assertSee('No challenges');
    }






    // -----------------------------------------------------------
    // test_the_list_pages_by_fifty
    // -----------------------------------------------------------
    //
    // Fifty challenges fit on a page; the fifty-first, the
    // oldest, is on page two.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_list_pages_by_fifty(): void
    {
        foreach (range(1, 51) as $n) {
            $this->makeChallenge(['name' => sprintf('Bulk %02d', $n)]);
        }
        $this->signInAdmin($this->makeAdmin(true));

        $this->get('/control-panel/challenges')
            ->assertSee('<td>Bulk 51</td>', false)
            ->assertSee('<td>Bulk 02</td>', false)
            ->assertDontSee('<td>Bulk 01</td>', false);
        $this->get('/control-panel/challenges?page=2')
            ->assertSee('<td>Bulk 01</td>', false)
            ->assertDontSee('<td>Bulk 02</td>', false);
    }






    // -----------------------------------------------------------
    // test_main_admins_get_the_visibility_switch
    // -----------------------------------------------------------
    //
    // A main admin sees each challenge's visibility as a
    // switch that posts the flip.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_main_admins_get_the_visibility_switch(): void
    {
        $visible = $this->makeChallenge();
        $hidden = $this->makeChallenge(['hidden' => 1]);

        $response = $this->signInAdmin($this->makeAdmin(true))->get('/control-panel/challenges');

        $response->assertSee("onClick=\"Post_Link('/control-panel/challenges/status/1/{$visible->id}');\"", false);
        $response->assertSee("onClick=\"Post_Link('/control-panel/challenges/status/0/{$hidden->id}');\"", false);
    }






    // -----------------------------------------------------------
    // test_lab_admins_get_edit_links_only_for_their_own
    // -----------------------------------------------------------
    //
    // A lab admin sees every challenge but gets the edit,
    // delete, Categories and Files links for its own ones
    // only, as the others' would all refuse it, and no
    // visibility switch.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_admins_get_edit_links_only_for_their_own(): void
    {
        $admin = $this->makeAdmin();
        $own = $this->makeChallenge(['name' => 'Own Task', 'created_by' => $admin->id]);
        $other = $this->makeChallenge(['name' => 'Other Task']);

        $response = $this->signInAdmin($admin)->get('/control-panel/challenges');

        $response->assertSee('<td>Other Task</td>', false);
        $response->assertSee("href=\"/control-panel/challenges/edit/{$own->id}\"", false);
        $response->assertDontSee("href=\"/control-panel/challenges/edit/{$other->id}\"", false);
        $response->assertSee("challenge_categories({$own->id})", false);
        $response->assertDontSee("challenge_categories({$other->id})", false);
        $response->assertSee("href=\"/control-panel/challenges/files/{$own->id}\"", false);
        $response->assertDontSee("href=\"/control-panel/challenges/files/{$other->id}\"", false);
        $response->assertDontSee('/control-panel/challenges/status/', false);
    }






    // -----------------------------------------------------------
    // test_the_create_form_offers_every_partner
    // -----------------------------------------------------------
    //
    // The new-challenge form posts to the store route and
    // offers every partner to credit.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_create_form_offers_every_partner(): void
    {
        $partner = $this->makePartner(['name' => 'CERT-LT']);

        $response = $this->signInAdmin($this->makeAdmin())->get('/control-panel/challenges/create');

        $response->assertOk();
        $response->assertSee('action="https://lab.knf.vu.lt/control-panel/challenges/store"', false);
        $response->assertSee("<option value=\"{$partner->id}\">CERT-LT</option>", false);
    }






    // -----------------------------------------------------------
    // test_a_main_admin_publishes_a_new_challenge_at_once
    // -----------------------------------------------------------
    //
    // A main admin's challenge is stored visible, credited to
    // that admin, with a fresh UUID as its public id and the
    // answer trimmed; the admin returns to the list.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_main_admin_publishes_a_new_challenge_at_once(): void
    {
        $admin = $this->makeAdmin(true);
        $form = $this->challengeForm(['answer' => '  packet_wizard  ']);

        $response = $this->signInAdmin($admin)->post('/control-panel/challenges/store', $form);

        $response->assertRedirect('/control-panel/challenges');
        $response->assertSessionHas('success', 'Created!');
        $challenge = Challenges::where('name', 'Packet Detective')->firstOrFail();
        $this->assertSame(0, $challenge->hidden);
        $this->assertSame($admin->id, $challenge->created_by);
        $this->assertSame($form['partner_id'], $challenge->partner_id);
        $this->assertSame(30, $challenge->points);
        $this->assertSame('packet_wizard', $challenge->answer);
        $this->assertSame($form['content'], $challenge->content);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $challenge->public_id);
    }






    // -----------------------------------------------------------
    // test_a_lab_admins_new_challenge_starts_hidden
    // -----------------------------------------------------------
    //
    // A lab admin's challenge is stored hidden, credited to
    // the lab admin.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_lab_admins_new_challenge_starts_hidden(): void
    {
        $admin = $this->makeAdmin();

        $this->signInAdmin($admin)->post('/control-panel/challenges/store', $this->challengeForm());

        $challenge = Challenges::where('name', 'Packet Detective')->firstOrFail();
        $this->assertSame(1, $challenge->hidden);
        $this->assertSame($admin->id, $challenge->created_by);
    }






    // -----------------------------------------------------------
    // test_the_edit_form_is_filled_with_the_challenge
    // -----------------------------------------------------------
    //
    // The edit form posts to the update route with the
    // challenge's id, and carries its current values — the
    // answer included, as admins may read it.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_edit_form_is_filled_with_the_challenge(): void
    {
        $challenge = $this->makeChallenge(['name' => 'Packet Detective', 'answer' => 'packet_wizard', 'points' => 40]);

        $response = $this->signInAdmin($this->makeAdmin(true))->get("/control-panel/challenges/edit/{$challenge->id}");

        $response->assertOk();
        $response->assertSee('action="https://lab.knf.vu.lt/control-panel/challenges/update"', false);
        $response->assertSee('name="name" type="text" value="Packet Detective"', false);
        $response->assertSee('name="answer" type="text" value="packet_wizard"', false);
        $response->assertSee('name="points" type="number" value="40"', false);
        $response->assertSee("name=\"uid\" type=\"hidden\" value=\"{$challenge->id}\"", false);
    }






    // -----------------------------------------------------------
    // test_an_update_rewrites_the_challenge
    // -----------------------------------------------------------
    //
    // The posted values replace the stored ones and the admin
    // returns to the list.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_update_rewrites_the_challenge(): void
    {
        $admin = $this->makeAdmin(true);
        $challenge = $this->makeChallenge(['created_by' => $admin->id]);
        $form = $this->challengeForm(['uid' => $challenge->id, 'name' => 'Renamed Task', 'points' => 75]);

        $response = $this->signInAdmin($admin)->post('/control-panel/challenges/update', $form);

        $response->assertRedirect('/control-panel/challenges');
        $response->assertSessionHas('success', 'Updated!');
        $challenge->refresh();
        $this->assertSame('Renamed Task', $challenge->name);
        $this->assertSame(75, $challenge->points);
        $this->assertSame('packet_wizard', $challenge->answer);
        $this->assertSame($form['partner_id'], $challenge->partner_id);
    }






    // -----------------------------------------------------------
    // test_changing_a_challenges_points_keeps_what_solvers_earned
    // -----------------------------------------------------------
    //
    // A solve keeps the points it gave when the challenge's
    // points change, in the solve and in the player's total;
    // only the players who solve it afterwards get the new
    // value.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_changing_a_challenges_points_keeps_what_solvers_earned(): void
    {
        $admin = $this->makeAdmin(true);
        $challenge = $this->makeChallenge(['created_by' => $admin->id, 'points' => 10]);
        $early = $this->makePlayer();
        $this->recordSolve($early, $challenge);

        $this->signInAdmin($admin)
            ->post('/control-panel/challenges/update', $this->challengeForm(['uid' => $challenge->id, 'points' => 40]))
            ->assertSessionHas('success', 'Updated!');
        $late = $this->makePlayer();
        $this->signIn($late)
            ->post("/user/challenge/{$challenge->public_id}", ['answer' => 'packet_wizard'])
            ->assertSessionHas('success', 'Your answer is correct! You received 40 points');

        $this->assertSame(10, ChallengesSubmitted::where(['user_id' => $early->id, 'challenge_id' => $challenge->id])->value('points_given'));
        $this->assertSame(10, $early->refresh()->points);
        $this->assertSame(40, $late->refresh()->points);
    }






    // -----------------------------------------------------------
    // test_a_lab_admin_manages_its_own_challenge
    // -----------------------------------------------------------
    //
    // A lab admin opens and updates a challenge it created.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_lab_admin_manages_its_own_challenge(): void
    {
        $admin = $this->makeAdmin();
        $challenge = $this->makeChallenge(['created_by' => $admin->id]);
        $this->signInAdmin($admin);

        $this->get("/control-panel/challenges/edit/{$challenge->id}")->assertOk();
        $this->post('/control-panel/challenges/update', $this->challengeForm(['uid' => $challenge->id, 'name' => 'Lab Task']))
            ->assertSessionHas('success', 'Updated!');

        $this->assertSame('Lab Task', $challenge->refresh()->name);
    }






    // -----------------------------------------------------------
    // test_a_lab_admin_cannot_touch_another_admins_challenge
    // -----------------------------------------------------------
    //
    // Opening, deleting, updating, re-categorising and adding
    // files to someone else's challenge all send a lab admin
    // back with the permissions error, and change nothing.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_lab_admin_cannot_touch_another_admins_challenge(): void
    {
        $challenge = $this->makeChallenge(['name' => 'Not Yours']);
        $this->signInAdmin($this->makeAdmin())->from('/control-panel/challenges');

        $this->get("/control-panel/challenges/edit/{$challenge->id}")->assertRedirect('/control-panel/challenges');
        $this->assertErrorFlashed(self::REFUSAL);
        $this->post("/control-panel/challenges/delete/{$challenge->id}")->assertRedirect('/control-panel/challenges');
        $this->assertErrorFlashed(self::REFUSAL);
        $this->post('/control-panel/challenges/update', $this->challengeForm(['uid' => $challenge->id, 'name' => 'Taken Over']));
        $this->assertErrorFlashed(self::REFUSAL);
        $this->post('/control-panel/challenges/categories/store', ['uid' => $challenge->id, 'item' => [2 => 2]]);
        $this->assertErrorFlashed(self::REFUSAL);
        $this->post('/control-panel/challenges_file/store', ['uid' => $challenge->id, 'name' => 'x', 'file_url' => 'https://files.test/x']);
        $this->assertErrorFlashed(self::REFUSAL);

        $this->assertSame('Not Yours', $challenge->refresh()->name);
        $this->assertSame(0, ChallengesCategories::where('challenge_id', $challenge->id)->count());
        $this->assertSame(0, ChallengesFiles::where('challenge_id', $challenge->id)->count());
    }






    // -----------------------------------------------------------
    // test_a_challenge_that_does_not_exist_is_refused
    // -----------------------------------------------------------
    //
    // An id that names no challenge is answered with "No
    // challenge!", in the address and in a posted form.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_challenge_that_does_not_exist_is_refused(): void
    {
        $this->signInAdmin($this->makeAdmin(true))->from('/control-panel/challenges');

        $this->get('/control-panel/challenges/edit/987654321')->assertRedirect('/control-panel/challenges');
        $this->assertErrorFlashed('No challenge!');
        $this->post('/control-panel/challenges/update', $this->challengeForm(['uid' => 987654321]));
        $this->assertErrorFlashed('No challenge!');
    }






    // -----------------------------------------------------------
    // test_deleting_a_challenge_removes_what_hangs_off_it
    // -----------------------------------------------------------
    //
    // The challenge goes with its files, categories, history,
    // submissions and group places, and the points it gave
    // come off its solvers' totals; another challenge's rows
    // stay, and so do the points it gave.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_deleting_a_challenge_removes_what_hangs_off_it(): void
    {
        $player = $this->makePlayer();
        $group = $this->makeGroup();
        $doomed = $this->makeChallenge(['points' => 40]);
        $kept = $this->makeChallenge();
        foreach ([$doomed, $kept] as $challenge) {
            ChallengesFiles::create(['challenge_id' => $challenge->id, 'name' => 'f', 'file_url' => 'https://files.test/f']);
            ChallengesCategories::create(['challenge_id' => $challenge->id, 'cat_id' => 1]);
            $this->assignToGroup($group, $challenge);
            $this->recordSolve($player, $challenge);
        }
        $other = $this->makePlayer();
        $this->recordSolve($other, $doomed);

        $response = $this->signInAdmin($this->makeAdmin(true))
            ->from('/control-panel/challenges')
            ->post("/control-panel/challenges/delete/{$doomed->id}");

        $response->assertRedirect('/control-panel/challenges');
        $response->assertSessionHas('success', 'Deleted!');
        $this->assertNull(Challenges::find($doomed->id));
        foreach ([ChallengesFiles::class, ChallengesCategories::class, GroupsChallenges::class, ChallengesHistory::class, ChallengesSubmitted::class] as $table) {
            $this->assertSame(0, $table::where('challenge_id', $doomed->id)->count(), $table);
            $this->assertSame(1, $table::where('challenge_id', $kept->id)->count(), $table);
        }
        $this->assertSame(10, $player->refresh()->points);
        $this->assertSame(0, $other->refresh()->points);
    }






    // -----------------------------------------------------------
    // test_deleting_a_challenge_takes_its_votes_along
    // -----------------------------------------------------------
    //
    // The challenge's rows in cyber_challenges_votes go with
    // it; another challenge's votes stay, the deleted one's
    // voters' included.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_deleting_a_challenge_takes_its_votes_along(): void
    {
        $doomed = $this->makeChallenge();
        $kept = $this->makeChallenge();
        $player = $this->makePlayer();
        $this->signIn($player)->post("/user/challenge/vote/up/{$doomed->public_id}");
        $this->signIn($player)->post("/user/challenge/vote/down/{$kept->public_id}");
        $this->signIn($this->makePlayer())->post("/user/challenge/vote/down/{$doomed->public_id}");

        $this->signInAdmin($this->makeAdmin(true))->post("/control-panel/challenges/delete/{$doomed->id}");

        $this->assertSame(0, DB::table('cyber_challenges_votes')->where('challenge_id', $doomed->id)->count());
        $this->assertSame([$player->id => 'down'], DB::table('cyber_challenges_votes')->where('challenge_id', $kept->id)->pluck('vote', 'user_id')->all());
    }






    // -----------------------------------------------------------
    // test_the_visibility_switch_flips_a_challenge
    // -----------------------------------------------------------
    //
    // A main admin hides a challenge and shows it again, and
    // returns to the list each time.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_visibility_switch_flips_a_challenge(): void
    {
        $challenge = $this->makeChallenge();
        $this->signInAdmin($this->makeAdmin(true))->from('/control-panel/challenges');

        $this->post("/control-panel/challenges/status/1/{$challenge->id}")->assertRedirect('/control-panel/challenges');
        $this->assertSame(1, $challenge->refresh()->hidden);

        $this->post("/control-panel/challenges/status/0/{$challenge->id}");
        $this->assertSame(0, $challenge->refresh()->hidden);
    }






    // -----------------------------------------------------------
    // test_lab_admins_cannot_switch_visibility
    // -----------------------------------------------------------
    //
    // Visibility is a main admin's call, even for a lab
    // admin's own challenge.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_admins_cannot_switch_visibility(): void
    {
        $admin = $this->makeAdmin();
        $challenge = $this->makeChallenge(['created_by' => $admin->id, 'hidden' => 1]);

        $this->signInAdmin($admin)->from('/control-panel/challenges')->post("/control-panel/challenges/status/0/{$challenge->id}");

        $this->assertErrorFlashed(self::REFUSAL);
        $this->assertSame(1, $challenge->refresh()->hidden);
    }






    // -----------------------------------------------------------
    // test_the_categories_dialog_ticks_the_current_categories
    // -----------------------------------------------------------
    //
    // The dialog names the challenge and lists every category
    // as a checkbox, the challenge's own ones ticked.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_categories_dialog_ticks_the_current_categories(): void
    {
        $challenge = $this->makeChallenge(['name' => 'Packet Detective']);
        ChallengesCategories::create(['challenge_id' => $challenge->id, 'cat_id' => 6]);

        $html = $this->signInAdmin($this->makeAdmin(true))
            ->get("/control-panel/dialog/challenges/categories/{$challenge->id}")
            ->assertOk()
            ->assertSee('<b>Packet Detective</b>', false)
            ->getContent();

        $this->assertMatchesRegularExpression('/name="item\[6\]" value="6"\s+checked\s*>/', $html);
        $this->assertMatchesRegularExpression('/name="item\[1\]" value="1"\s+>/', $html);
    }






    // -----------------------------------------------------------
    // test_saving_categories_replaces_the_previous_choice
    // -----------------------------------------------------------
    //
    // The ticked categories become the challenge's categories,
    // replacing the earlier choice entirely.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_saving_categories_replaces_the_previous_choice(): void
    {
        $challenge = $this->makeChallenge();
        ChallengesCategories::create(['challenge_id' => $challenge->id, 'cat_id' => 1]);

        $response = $this->signInAdmin($this->makeAdmin(true))
            ->from('/control-panel/challenges')
            ->post('/control-panel/challenges/categories/store', ['uid' => $challenge->id, 'item' => [3 => 3, 5 => 5]]);

        $response->assertRedirect('/control-panel/challenges');
        $response->assertSessionHas('success', 'Categoried updated!');
        $this->assertSame([3, 5], ChallengesCategories::where('challenge_id', $challenge->id)->orderBy('cat_id')->pluck('cat_id')->all());
    }






    // -----------------------------------------------------------
    // test_editing_a_challenge_keeps_its_author
    // -----------------------------------------------------------
    //
    // A main admin correcting a lab admin's challenge leaves it
    // the lab admin's: the author stays, and can still open it.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_editing_a_challenge_keeps_its_author(): void
    {
        $author = $this->makeAdmin();
        $challenge = $this->makeChallenge(['created_by' => $author->id]);
        $form = [
            'uid'        => $challenge->id,
            'name'       => 'Corrected Name',
            'partner_id' => $challenge->partner_id,
            'descr'      => $challenge->descr,
            'content'    => $challenge->content,
            'answer'     => $challenge->answer,
            'points'     => $challenge->points,
        ];

        $this->signInAdmin($this->makeAdmin(true))->post('/control-panel/challenges/update', $form);

        $this->assertSame('Corrected Name', $challenge->refresh()->name);
        $this->assertSame($author->id, $challenge->created_by);
        $this->signInAdmin($author)->get("/control-panel/challenges/edit/{$challenge->id}")->assertOk();
    }






    // -----------------------------------------------------------
    // test_all_categories_can_be_taken_off_a_challenge
    // -----------------------------------------------------------
    //
    // Saving the categories dialog with every box unticked — the
    // form then sends no item field at all — leaves the challenge
    // without categories and goes back.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_all_categories_can_be_taken_off_a_challenge(): void
    {
        $challenge = $this->makeChallenge();
        ChallengesCategories::create(['challenge_id' => $challenge->id, 'cat_id' => 1]);

        $response = $this->signInAdmin($this->makeAdmin(true))
            ->from('/control-panel/challenges')
            ->post('/control-panel/challenges/categories/store', ['uid' => $challenge->id]);

        $response->assertRedirect('/control-panel/challenges');
        $this->assertSame(0, ChallengesCategories::where('challenge_id', $challenge->id)->count());
    }






    // -----------------------------------------------------------
    // test_an_incomplete_challenge_form_is_refused
    // -----------------------------------------------------------
    //
    // A challenge form missing fields goes back with an error for
    // each of them and what was typed, and stores nothing.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_incomplete_challenge_form_is_refused(): void
    {
        $response = $this->signInAdmin($this->makeAdmin(true))
            ->from('/control-panel/challenges/create')
            ->post('/control-panel/challenges/store', ['name' => 'Half a challenge']);

        $response->assertRedirect('/control-panel/challenges/create');
        $response->assertSessionHasErrors(['partner_id', 'descr', 'content', 'answer', 'points']);
        $this->assertSame('Half a challenge', session()->getOldInput('name'));
        $this->assertSame(0, Challenges::where('name', 'Half a challenge')->count());
    }






    // -----------------------------------------------------------
    // test_a_lab_admins_challenge_carries_no_script
    // -----------------------------------------------------------
    //
    // The content a lab admin saves loses its scripts and event
    // handlers before it is stored, so the challenge page that
    // prints it as HTML runs nothing in a player's browser;
    // the formatting around them stays.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_lab_admins_challenge_carries_no_script(): void
    {
        $admin = $this->makeAdmin();
        $group = $this->makeGroup($admin);
        $player = $this->makeSsoPlayer();
        $this->joinGroup($group, $player);
        $this->signInAdmin($admin)->post('/control-panel/challenges/store', $this->challengeForm([
            'name'    => 'Scripted Task',
            'content' => '<p>Formatting stays</p><script>alert(document.domain)</script><img src="x" onerror="alert(2)">',
        ]));
        $challenge = Challenges::where('name', 'Scripted Task')->firstOrFail();
        $this->assignToGroup($group, $challenge);

        $page = $this->signIn($player)->get("/user/challenge/{$challenge->public_id}/{$group->public_id}");

        $page->assertOk();
        $page->assertSee('<p>Formatting stays</p>', false);
        $page->assertDontSee('<script>alert(document.domain)</script>', false);
        $page->assertDontSee('onerror=', false);
    }






    // -----------------------------------------------------------
    // test_a_lab_admins_update_is_cleaned_too
    // -----------------------------------------------------------
    //
    // Changing its own challenge, a lab admin cannot bring a
    // script or a javascript: link in either.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_lab_admins_update_is_cleaned_too(): void
    {
        $admin = $this->makeAdmin();
        $challenge = $this->makeChallenge(['created_by' => $admin->id]);

        $this->signInAdmin($admin)->post('/control-panel/challenges/update', $this->challengeForm([
            'uid'     => $challenge->id,
            'content' => '<p>Read <a href="javascript:alert(1)">this</a></p><svg onload="alert(3)"></svg><script>alert(4)</script>',
        ]))->assertSessionHas('success', 'Updated!');

        $content = $challenge->refresh()->content;
        $this->assertStringContainsString('this</a></p>', $content);
        $this->assertStringNotContainsString('javascript:', $content);
        $this->assertStringNotContainsString('alert', $content);
    }






    // -----------------------------------------------------------
    // test_a_lab_admins_formatting_survives_the_cleaning
    // -----------------------------------------------------------
    //
    // What the editor produces is kept: headings, aligned and
    // coloured text, lists, tables with their classes, links
    // that open in a new tab and a pasted picture embedded as
    // a data: address.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_lab_admins_formatting_survives_the_cleaning(): void
    {
        $pixel = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
        $admin = $this->makeAdmin();

        $this->signInAdmin($admin)->post('/control-panel/challenges/store', $this->challengeForm([
            'content' => '<h2>Task</h2>'
                . '<p style="text-align: center;"><b>Bold</b>, <i>italic</i> and <span style="color: rgb(255, 0, 0);">red</span></p>'
                . '<ul><li>one</li><li>two</li></ul>'
                . '<table class="table table-bordered"><tbody><tr><td>cell</td></tr></tbody></table>'
                . '<p><a href="https://files.test/readme" target="_blank">readme</a></p>'
                . '<p><img src="' . $pixel . '" style="width: 50%;"></p>',
        ]));

        $content = Challenges::where('name', 'Packet Detective')->value('content');
        $this->assertStringContainsString('<h2>Task</h2>', $content);
        $this->assertStringContainsString('<p style="text-align:center;"><b>Bold</b>, <i>italic</i> and <span style="color:', $content);
        $this->assertStringContainsString('<ul><li>one</li><li>two</li></ul>', $content);
        $this->assertStringContainsString('<table class="table table-bordered"><tbody><tr><td>cell</td></tr></tbody></table>', $content);
        $this->assertMatchesRegularExpression('#<a href="https://files.test/readme" target="_blank"[^>]*>readme</a>#', $content);
        $this->assertStringContainsString('src="' . $pixel . '"', $content);
    }






    // -----------------------------------------------------------
    // test_a_main_admins_content_is_kept_as_written
    // -----------------------------------------------------------
    //
    // A main admin's content is stored exactly as sent,
    // scripts included: main admins are trusted with the
    // whole site already.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_main_admins_content_is_kept_as_written(): void
    {
        $content = '<p>Interactive</p><script>document.title = "task";</script>';

        $this->signInAdmin($this->makeAdmin(true))->post('/control-panel/challenges/store', $this->challengeForm(['content' => $content]));

        $this->assertSame($content, Challenges::where('name', 'Packet Detective')->value('content'));
    }






    // -----------------------------------------------------------
    // test_an_incomplete_challenge_update_is_refused
    // -----------------------------------------------------------
    //
    // A challenge update missing fields goes back with an error
    // for each of them and what was typed, and the challenge
    // keeps every field — its flag above all.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_incomplete_challenge_update_is_refused(): void
    {
        $challenge = $this->makeChallenge(['name' => 'Kept Task', 'answer' => 'kept_flag']);

        $response = $this->signInAdmin($this->makeAdmin(true))
            ->from("/control-panel/challenges/edit/{$challenge->id}")
            ->post('/control-panel/challenges/update', ['uid' => $challenge->id, 'name' => 'Half an update']);

        $response->assertRedirect("/control-panel/challenges/edit/{$challenge->id}");
        $response->assertSessionHasErrors(['partner_id', 'descr', 'content', 'answer', 'points']);
        $this->assertSame('Half an update', session()->getOldInput('name'));
        $challenge->refresh();
        $this->assertSame('Kept Task', $challenge->name);
        $this->assertSame('kept_flag', $challenge->answer);
    }






    // -----------------------------------------------------------
    // test_the_visibility_switch_takes_only_0_or_1
    // -----------------------------------------------------------
    //
    // A hand-made status other than 0 or 1 answers 404 and
    // leaves the challenge's hidden column alone; written as
    // given, it would leave the list without a switch for the
    // challenge.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_visibility_switch_takes_only_0_or_1(): void
    {
        $challenge = $this->makeChallenge();
        $this->signInAdmin($this->makeAdmin(true));

        $this->post("/control-panel/challenges/status/7/{$challenge->id}")->assertNotFound();

        $this->assertSame(0, $challenge->refresh()->hidden);
    }






    // -----------------------------------------------------------
    // test_the_categories_dialog_is_for_the_challenges_owner
    // -----------------------------------------------------------
    //
    // A lab admin opens the categories dialog of its own
    // challenge, while the dialog of another admin's shows
    // the permissions error instead, and an unknown
    // challenge's "No challenge!" rather than a server error.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_categories_dialog_is_for_the_challenges_owner(): void
    {
        $admin = $this->makeAdmin();
        $own = $this->makeChallenge(['name' => 'Own Task', 'created_by' => $admin->id]);
        $other = $this->makeChallenge(['name' => 'Their Task']);
        $this->signInAdmin($admin)->from('/control-panel/challenges');

        $this->get("/control-panel/dialog/challenges/categories/{$own->id}")->assertOk()->assertSee('Own Task');
        $this->assertDialogRefused($this->get("/control-panel/dialog/challenges/categories/{$other->id}"), self::REFUSAL);
        $this->assertDialogRefused($this->get('/control-panel/dialog/challenges/categories/987654321'), 'No challenge!');
    }






    // -----------------------------------------------------------
    // test_deleting_a_challenge_takes_it_out_of_the_shared_history
    // -----------------------------------------------------------
    //
    // Once a challenge is deleted, the shared history in the
    // players' sidebar no longer lists its solves.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_deleting_a_challenge_takes_it_out_of_the_shared_history(): void
    {
        $challenge = $this->makeChallenge(['name' => 'Doomed Task']);
        $this->signIn($this->makePlayer())->post("/user/challenge/{$challenge->public_id}", ['answer' => $challenge->answer]);
        $this->assertStringContainsString('Doomed Task', json_encode(ChallengesHistory::feed()));

        $this->signInAdmin($this->makeAdmin(true))->post("/control-panel/challenges/delete/{$challenge->id}");

        $this->assertStringNotContainsString('Doomed Task', json_encode(ChallengesHistory::feed()));
    }






    // -----------------------------------------------------------
    // test_renaming_or_hiding_a_challenge_updates_the_shared_history
    // -----------------------------------------------------------
    //
    // A rename shows in the shared history at once, and hiding
    // the challenge takes its solves out of it, as the history
    // lists only challenges every player may see.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_renaming_or_hiding_a_challenge_updates_the_shared_history(): void
    {
        $admin = $this->makeAdmin(true);
        $challenge = $this->makeChallenge(['name' => 'Old Name', 'created_by' => $admin->id, 'answer' => 'packet_wizard']);
        $this->signIn($this->makePlayer())->post("/user/challenge/{$challenge->public_id}", ['answer' => 'packet_wizard']);

        $this->signInAdmin($admin)->post('/control-panel/challenges/update', $this->challengeForm(['uid' => $challenge->id, 'name' => 'New Name']));
        $history = json_encode(ChallengesHistory::feed());
        $this->assertStringContainsString('New Name', $history);
        $this->assertStringNotContainsString('Old Name', $history);

        $this->post("/control-panel/challenges/status/1/{$challenge->id}");
        $this->assertStringNotContainsString('New Name', json_encode(ChallengesHistory::feed()));
    }






    // -----------------------------------------------------------
    // test_two_deletes_take_the_points_off_once
    // -----------------------------------------------------------
    //
    // Two admins deleting the same challenge at the same moment
    // take its points off the solvers once: the delete locks the
    // challenge's row before it touches the totals. The second
    // delete runs in full right after the first one's first step
    // on the challenge, where an unlocked delete let it take the
    // points off a second time.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_two_deletes_take_the_points_off_once(): void
    {
        $player = $this->makePlayer();
        $doomed = $this->makeChallenge(['points' => 40]);
        $this->recordSolve($player, $this->makeChallenge(['points' => 10]));
        $this->recordSolve($player, $doomed);
        $this->signInAdmin($this->makeAdmin(true));
        $interleaved = false;
        DB::listen(function (QueryExecuted $query) use ($doomed, &$interleaved) {
            $sql = strtolower($query->sql);
            if (!$interleaved && (str_contains($sql, 'for update') || str_starts_with($sql, 'update `cyber_users`'))) {
                $interleaved = true;
                app(ChallengesController::class)->destroy($doomed->id);
            }
        });

        $this->post("/control-panel/challenges/delete/{$doomed->id}");

        $this->assertTrue($interleaved);
        $this->assertSame(10, $player->refresh()->points);
    }






    // -----------------------------------------------------------
    // test_a_name_or_flag_longer_than_its_column_is_refused
    // -----------------------------------------------------------
    //
    // A name or a flag holds up to the 255 characters of its
    // column. A longer one is refused with the error, rather
    // than stored cut short, which would leave a flag no player
    // could ever match.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_name_or_flag_longer_than_its_column_is_refused(): void
    {
        $admin = $this->makeAdmin(true);
        $challenge = $this->makeChallenge(['created_by' => $admin->id, 'name' => 'Kept Name']);
        $this->signInAdmin($admin)->from('/control-panel/challenges/create');

        $this->post('/control-panel/challenges/store', $this->challengeForm(['answer' => str_repeat('f', 256)]))->assertSessionHasErrors('answer');
        $this->post('/control-panel/challenges/store', $this->challengeForm(['name' => str_repeat('n', 256)]))->assertSessionHasErrors('name');
        $this->post('/control-panel/challenges/update', $this->challengeForm(['uid' => $challenge->id, 'name' => str_repeat('n', 256)]))->assertSessionHasErrors('name');
        $this->assertSame(1, Challenges::count());
        $this->assertSame('Kept Name', $challenge->refresh()->name);

        $this->post('/control-panel/challenges/store', $this->challengeForm(['answer' => str_repeat('f', 255)]))->assertSessionHasNoErrors();
        $this->assertSame(2, Challenges::count());
    }






    // -----------------------------------------------------------
    // test_a_deleted_partner_stays_off_an_edited_challenge
    // -----------------------------------------------------------
    //
    // The edit form of a challenge whose partner was deleted
    // selects "No partner (deleted)" rather than the first
    // partner of the list, so saving the form keeps the
    // challenge without a partner.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_deleted_partner_stays_off_an_edited_challenge(): void
    {
        $admin = $this->makeAdmin(true);
        $this->makePartner(['name' => 'Still Here']);
        $gone = $this->makePartner(['name' => 'Gone Partner']);
        $challenge = $this->makeChallenge(['created_by' => $admin->id, 'partner_id' => $gone->id]);
        $gone->delete();

        $this->signInAdmin($admin)->get("/control-panel/challenges/edit/{$challenge->id}")
            ->assertSee("<option value=\"{$gone->id}\" selected=\"selected\">No partner (deleted)</option>", false);
        $this->post('/control-panel/challenges/update', $this->challengeForm(['uid' => $challenge->id, 'partner_id' => $gone->id]));

        $this->assertSame($gone->id, $challenge->refresh()->partner_id);
    }






    // -----------------------------------------------------------
    // test_a_search_for_0_is_a_search
    // -----------------------------------------------------------
    //
    // The search term 0 narrows the list like any other term,
    // rather than counting as no search at all.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_search_for_0_is_a_search(): void
    {
        $this->makeChallenge(['name' => 'Port 80 Scan']);
        $this->makeChallenge(['name' => 'Hidden Flag']);

        $response = $this->signInAdmin($this->makeAdmin(true))->get('/control-panel/challenges?q=0');

        $response->assertSee('<td>Port 80 Scan</td>', false);
        $response->assertDontSee('<td>Hidden Flag</td>', false);
    }






    // -----------------------------------------------------------
    // test_a_challenge_field_in_the_address_never_replaces_the_forms
    // -----------------------------------------------------------
    //
    // A name put into the form's address, however long, is
    // passed over: the challenge is stored with the name of
    // the form, the one the rules checked.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_challenge_field_in_the_address_never_replaces_the_forms(): void
    {
        $this->signInAdmin($this->makeAdmin(true))->from('/control-panel/challenges/create');

        $this->post('/control-panel/challenges/store?name=' . str_repeat('n', 300), $this->challengeForm());

        $this->assertTrue(Challenges::where('name', 'Packet Detective')->exists());
    }






    // -----------------------------------------------------------
    // test_a_category_save_without_a_list_keeps_the_categories
    // -----------------------------------------------------------
    //
    // An item field sent as a single value rather than the
    // list of ticked boxes, which only a hand-made request
    // does, goes back with the error and leaves the
    // challenge's categories as they were.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_category_save_without_a_list_keeps_the_categories(): void
    {
        $challenge = $this->makeChallenge();
        ChallengesCategories::create(['challenge_id' => $challenge->id, 'cat_id' => 1]);

        $this->signInAdmin($this->makeAdmin(true))
            ->from('/control-panel/challenges')
            ->post('/control-panel/challenges/categories/store', ['uid' => $challenge->id, 'item' => 'x'])
            ->assertRedirect('/control-panel/challenges');

        $this->assertSame(1, ChallengesCategories::where('challenge_id', $challenge->id)->count());
    }






    // -----------------------------------------------------------
    // test_a_category_save_passes_over_unknown_categories
    // -----------------------------------------------------------
    //
    // A ticked key that names no category is passed over, so
    // the challenge gets only categories that exist.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_category_save_passes_over_unknown_categories(): void
    {
        $challenge = $this->makeChallenge();

        $this->signInAdmin($this->makeAdmin(true))
            ->from('/control-panel/challenges')
            ->post('/control-panel/challenges/categories/store', ['uid' => $challenge->id, 'item' => [4 => 4, 987654321 => 987654321]]);

        $this->assertEquals([4], ChallengesCategories::where('challenge_id', $challenge->id)->pluck('cat_id')->all());
    }






    // -----------------------------------------------------------
    // test_a_double_clicked_category_save_stores_each_category_once
    // -----------------------------------------------------------
    //
    // The save replaces the categories in a transaction that
    // first locks the challenge's row. Here the second save of
    // a double click runs in full where it would wait, at the
    // first one's lock, and the challenge still ends with each
    // category once.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_double_clicked_category_save_stores_each_category_once(): void
    {
        $challenge = $this->makeChallenge();
        $form = ['uid' => $challenge->id, 'item' => [4 => 4]];
        $this->signInAdmin($this->makeAdmin(true))->from('/control-panel/challenges');
        $interleaved = false;
        DB::listen(function (QueryExecuted $query) use ($form, &$interleaved) {
            $sql = strtolower($query->sql);
            if (!$interleaved && (str_contains($sql, 'for update') || str_starts_with($sql, 'delete from `cyber_challenges_categories`'))) {
                $interleaved = true;
                app(ChallengesController::class)->categories_store(Request::create('/', 'POST', $form));
            }
        });

        $this->post('/control-panel/challenges/categories/store', $form);

        $this->assertTrue($interleaved);
        $this->assertSame(1, ChallengesCategories::where('challenge_id', $challenge->id)->count());
    }






    // -----------------------------------------------------------
    // test_challenge_points_are_a_whole_number_in_range
    // -----------------------------------------------------------
    //
    // Points are a whole number from 0 to 100000, on a new
    // challenge and on an edit alike: MySQL, running
    // non-strict, would store a word as 0 and clip a number
    // past its integers, and negative points would take points
    // off every solver.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_challenge_points_are_a_whole_number_in_range(): void
    {
        $this->signInAdmin($this->makeAdmin(true))->from('/control-panel/challenges/create');

        foreach (['-100', 'abc', '3000000000'] as $n => $points) {
            $this->post('/control-panel/challenges/store', $this->challengeForm(['name' => "Odd points {$n}", 'points' => $points]))
                ->assertSessionHasErrors('points');
        }
        $challenge = $this->makeChallenge(['points' => 30]);
        $this->post('/control-panel/challenges/update', $this->challengeForm(['uid' => $challenge->id, 'points' => '-5']))
            ->assertSessionHasErrors('points');

        $this->assertSame(0, Challenges::where('name', 'like', 'Odd points %')->count());
        $this->assertEquals(30, $challenge->refresh()->points);
    }






    // -----------------------------------------------------------
    // test_a_deleted_challenges_page_sends_the_admin_to_the_list
    // -----------------------------------------------------------
    //
    // A challenge's edit form opened after another admin
    // deleted the challenge goes to the challenges list with
    // "No challenge!", even when the browser comes from that
    // same form, as after an update.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_deleted_challenges_page_sends_the_admin_to_the_list(): void
    {
        $challenge = $this->makeChallenge();
        $page = "/control-panel/challenges/edit/{$challenge->id}";
        $this->signInAdmin($this->makeAdmin(true))->post("/control-panel/challenges/delete/{$challenge->id}");

        $this->from($page)->get($page)->assertRedirect('/control-panel/challenges');
        $this->assertErrorFlashed('No challenge!');
    }






    // -----------------------------------------------------------
    // test_a_list_page_past_the_end_shows_the_last_page
    // -----------------------------------------------------------
    //
    // As every paged list of the panel, the challenges list
    // shows its last page for a page past its end.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_list_page_past_the_end_shows_the_last_page(): void
    {
        $this->makeChallenge(['name' => 'Packet Detective']);

        $this->signInAdmin($this->makeAdmin(true))->get('/control-panel/challenges?page=5')->assertSee('Packet Detective');
    }






    // -----------------------------------------------------------
    // test_a_refused_challenge_page_goes_to_the_list
    // -----------------------------------------------------------
    //
    // A challenge's page refused to a lab admin — another
    // admin's challenge, or one that no longer exists — goes
    // to the challenges list with the error, even when the
    // browser comes from that very page: an admin who lost the
    // right to a challenge while its edit form was open, and
    // saved it, would otherwise be sent back to the page again
    // and again.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_refused_challenge_page_goes_to_the_list(): void
    {
        $other = $this->makeChallenge();
        $this->signInAdmin($this->makeAdmin());

        foreach (["/control-panel/challenges/edit/{$other->id}", "/control-panel/challenges/files/{$other->id}"] as $page) {
            $this->from($page)->get($page)->assertRedirect('/control-panel/challenges');
            $this->assertErrorFlashed(self::REFUSAL);
        }
        $this->from('/control-panel/challenges/edit/987654321')->get('/control-panel/challenges/edit/987654321')
            ->assertRedirect('/control-panel/challenges');
        $this->assertErrorFlashed('No challenge!');
    }






    // -----------------------------------------------------------
    // test_a_refused_form_goes_back_and_a_dialog_shows_the_refusal
    // -----------------------------------------------------------
    //
    // A refused form goes back to the page it was sent from —
    // here the dashboard, so going back is told apart from
    // going to the list — and a refused dialog, which the panel
    // loads into the modal of the page it was opened from,
    // shows the error there, whether the challenge is another
    // admin's or gone.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_refused_form_goes_back_and_a_dialog_shows_the_refusal(): void
    {
        $other = $this->makeChallenge(['name' => 'Their Task']);
        $this->signInAdmin($this->makeAdmin())->from('/control-panel/dashboard');

        $this->assertDialogRefused($this->get("/control-panel/dialog/challenges/categories/{$other->id}"), self::REFUSAL);
        $this->assertDialogRefused($this->get('/control-panel/dialog/challenges/categories/987654321'), 'No challenge!');
        $this->post("/control-panel/challenges/delete/{$other->id}")->assertRedirect('/control-panel/dashboard');
        $this->assertErrorFlashed(self::REFUSAL);
        $this->post('/control-panel/challenges/delete/987654321')->assertRedirect('/control-panel/dashboard');
        $this->assertErrorFlashed('No challenge!');
        $this->post('/control-panel/challenges/update', $this->challengeForm(['uid' => $other->id, 'name' => 'Taken Over']))
            ->assertRedirect('/control-panel/dashboard');
        $this->assertErrorFlashed(self::REFUSAL);

        $this->assertSame('Their Task', $other->refresh()->name);
    }






    // -----------------------------------------------------------
    // test_challenge_points_take_both_ends_of_the_range
    // -----------------------------------------------------------
    //
    // The points range includes both its ends: 0 and 100000
    // are taken, on a new challenge and on an edit, and 100001
    // is refused.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_challenge_points_take_both_ends_of_the_range(): void
    {
        $this->signInAdmin($this->makeAdmin(true))->from('/control-panel/challenges/create');

        $this->post('/control-panel/challenges/store', $this->challengeForm(['name' => 'Free task', 'points' => '0']))->assertSessionHasNoErrors();
        $this->post('/control-panel/challenges/store', $this->challengeForm(['name' => 'Grand task', 'points' => '100000']))->assertSessionHasNoErrors();
        $this->post('/control-panel/challenges/store', $this->challengeForm(['name' => 'Too grand task', 'points' => '100001']))->assertSessionHasErrors('points');
        $challenge = $this->makeChallenge(['points' => 30]);
        $this->post('/control-panel/challenges/update', $this->challengeForm(['uid' => $challenge->id, 'points' => '100000']))->assertSessionHasNoErrors();

        $this->assertEquals(0, Challenges::where('name', 'Free task')->value('points'));
        $this->assertEquals(100000, Challenges::where('name', 'Grand task')->value('points'));
        $this->assertFalse(Challenges::where('name', 'Too grand task')->exists());
        $this->assertEquals(100000, $challenge->refresh()->points);
    }






    // -----------------------------------------------------------
    // test_the_search_takes_underscores_and_percents_as_typed
    // -----------------------------------------------------------
    //
    // The challenge search finds the words as typed: an
    // underscore matches an underscore only, not any character.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_search_takes_underscores_and_percents_as_typed(): void
    {
        $this->makeChallenge(['name' => 'Kq_search task']);
        $this->makeChallenge(['name' => 'Kqxsearch task']);

        $page = $this->signInAdmin($this->makeAdmin(true))->get('/control-panel/challenges?q=Kq_search')->getContent();

        $this->assertStringContainsString('Kq_search task', $page);
        $this->assertStringNotContainsString('Kqxsearch task', $page);
    }






    // -----------------------------------------------------------
    // test_a_form_refused_with_lists_for_fields_shows_again
    // -----------------------------------------------------------
    //
    // A challenge form sent with lists for the name and the
    // partner — only a hand-made request sends them — is
    // refused, and the form shows again with those fields
    // empty, not a 500.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_form_refused_with_lists_for_fields_shows_again(): void
    {
        $this->signInAdmin($this->makeAdmin(true))->from('/control-panel/challenges/create')
            ->post('/control-panel/challenges/store', $this->challengeForm(['name' => ['Task'], 'partner_id' => ['1'], 'points' => 'many']));

        $page = $this->get('/control-panel/challenges/create');

        $page->assertOk();
        $page->assertSee('name="name" type="text" value=""', false);
    }






    // -----------------------------------------------------------
    // test_a_list_for_a_text_field_is_refused
    // -----------------------------------------------------------
    //
    // A challenge form with a list for a text field or the
    // partner, or for the uid of the challenge it edits — only
    // a hand-made request sends one — goes back with the error
    // and stores nothing, rather than ending the save on a
    // 500.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_list_for_a_text_field_is_refused(): void
    {
        $challenge = $this->makeChallenge(['name' => 'Kept Name']);
        $this->signInAdmin($this->makeAdmin(true))->from('/control-panel/challenges/create');

        foreach (['name', 'partner_id', 'descr', 'content', 'answer'] as $field) {
            $form = $this->challengeForm();
            $form[$field] = [$form[$field]];
            $this->post('/control-panel/challenges/store', $form)->assertRedirect('/control-panel/challenges/create')->assertSessionHasErrors($field);
        }
        $this->post('/control-panel/challenges/update', $this->challengeForm(['uid' => [$challenge->id]]))->assertSessionHasErrors('uid');

        $this->assertSame(1, Challenges::count());
        $this->assertSame('Kept Name', $challenge->refresh()->name);
    }
}
