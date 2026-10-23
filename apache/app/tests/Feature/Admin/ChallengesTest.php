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
//  hanging off it. The challenge files have a test class of
//  their own, ChallengeFilesTest.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Challenges;
use App\Models\ChallengesCategories;
use App\Models\ChallengesFiles;
use App\Models\ChallengesHistory;
use App\Models\ChallengesSubmitted;
use App\Models\GroupsChallenges;
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
    // A lab admin sees every challenge but gets edit links for
    // its own ones only, and no visibility switch.
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
    // submissions and group places; another challenge's rows
    // stay, and so do the points players already earned.
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
        $this->assertSame(50, $player->refresh()->points);
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
}
