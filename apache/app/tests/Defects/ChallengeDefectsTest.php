<?php

// -----------------------------------------------------------
//  [*] ChallengeDefectsTest — open defects in challenge admin
//
//  Each test states the behaviour the lab wants where the
//  control panel's challenge pages fall short today, under a
//  LAB-nn id; KnownDefects reports it incomplete while the
//  defect is open and fails it once a fix lands. The banner
//  of every test gives the severity, the mechanism with file
//  and line, and the fix.
//
//  Used by:
//    - PHPUnit — the Defects suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Defects;

use App\Models\Challenges;
use App\Models\ChallengesCategories;
use App\Models\ChallengesFiles;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\KnownDefects;
use Tests\TestCase;








// -----------------------------------------------------------
// ChallengeDefectsTest
// -----------------------------------------------------------
//
// LAB-06 to LAB-10 and LAB-29.
//
// Used by:
//   - PHPUnit — the Defects suite (runTests.sh)
// -----------------------------------------------------------

final class ChallengeDefectsTest extends TestCase
{

    use KnownDefects;






    // -----------------------------------------------------------
    // test_lab_06_a_file_link_can_be_edited_and_deleted
    // -----------------------------------------------------------
    //
    // Known bug LAB-06 (medium): a challenge's file link can
    // be neither edited nor deleted. The two routes run the
    // r_exists challenge check, which reads the link's id as
    // a challenge id (routes/web.php, the challenges_file/
    // update and challenges/files/delete routes; the update
    // check came with the "Fix upload validation" commit):
    // unless a challenge happens to share the link's id, the
    // answer is "No challenge!", and a lab admin is checked
    // against the wrong challenge. Fix: check the challenge
    // the link belongs to.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_06_a_file_link_can_be_edited_and_deleted(): void
    {
        $challenge = $this->makeChallenge();
        $edited = ChallengesFiles::forceCreate(['id' => 2000000001, 'challenge_id' => $challenge->id, 'name' => 'old.pcap', 'file_url' => 'https://files.test/old']);
        $deleted = ChallengesFiles::forceCreate(['id' => 2000000002, 'challenge_id' => $challenge->id, 'name' => 'gone.pcap', 'file_url' => 'https://files.test/gone']);
        $this->signInAdmin($this->makeAdmin(true));

        $this->post('/control-panel/challenges_file/update', ['uid' => $edited->id, 'name' => 'new.pcap', 'file_url' => 'https://files.test/new']);
        $this->get("/control-panel/challenges/files/delete/{$deleted->id}");

        $this->assertDefectStillOpen('LAB-06', function () use ($edited, $deleted) {
            $this->assertSame('new.pcap', $edited->refresh()->name, 'the link was not edited');
            $this->assertNull(ChallengesFiles::find($deleted->id), 'the link was not deleted');
        });
    }






    // -----------------------------------------------------------
    // test_lab_07_editing_a_challenge_keeps_its_author
    // -----------------------------------------------------------
    //
    // Known bug LAB-07 (medium): saving a challenge hands it
    // to whoever saved it. Admins\ChallengesController::update
    // (line 341) writes the editing admin's id into
    // created_by, so when a main admin corrects a lab admin's
    // challenge, the lab admin can no longer open, change or
    // delete it. Fix: leave created_by out of the update.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_07_editing_a_challenge_keeps_its_author(): void
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
        $page = $this->signInAdmin($author)->get("/control-panel/challenges/edit/{$challenge->id}");

        $this->assertDefectStillOpen('LAB-07', function () use ($challenge, $author, $page) {
            $this->assertSame($author->id, $challenge->refresh()->created_by, 'the challenge changed hands');
            $page->assertOk();
        });
    }






    // -----------------------------------------------------------
    // test_lab_08_all_categories_can_be_taken_off_a_challenge
    // -----------------------------------------------------------
    //
    // Known bug LAB-08 (low): saving the categories dialog
    // with every box unticked ends in a server error instead
    // of clearing the categories — the old ones are deleted
    // first, then count() fails on the missing item list
    // (Admins\ChallengesController::categories_store, line
    // 453). Fix: treat a missing list as an empty one.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_08_all_categories_can_be_taken_off_a_challenge(): void
    {
        $challenge = $this->makeChallenge();
        ChallengesCategories::create(['challenge_id' => $challenge->id, 'cat_id' => 1]);

        $response = $this->signInAdmin($this->makeAdmin(true))
            ->from('/control-panel/challenges')
            ->post('/control-panel/challenges/categories/store', ['uid' => $challenge->id]);

        $this->assertDefectStillOpen('LAB-08', function () use ($response, $challenge) {
            $response->assertRedirect('/control-panel/challenges');
            $this->assertSame(0, ChallengesCategories::where('challenge_id', $challenge->id)->count());
        });
    }






    // -----------------------------------------------------------
    // test_lab_09_only_web_links_are_accepted_as_files
    // -----------------------------------------------------------
    //
    // Known bug LAB-09 (low): the file-link rule
    // url:http,https restricts nothing — the url rule of the
    // installed Laravel (10.6) takes no protocol list and
    // ignores it — so ftp, smb, file and the rest of
    // Laravel's long scheme list are stored and shown to
    // players as links. javascript: links stay refused, as
    // that scheme is not on the list. Fix: a Laravel release
    // whose url rule reads protocols, or a
    // starts_with:http://,https:// rule next to it.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    #[DataProvider('otherSchemes')]
    public function test_lab_09_only_web_links_are_accepted_as_files(string $url): void
    {
        $challenge = $this->makeChallenge();

        $response = $this->signInAdmin($this->makeAdmin(true))
            ->post('/control-panel/challenges_file/store', ['uid' => $challenge->id, 'name' => 'dump.zip', 'file_url' => $url]);

        $this->assertDefectStillOpen('LAB-09', function () use ($response, $challenge) {
            $response->assertSessionHasErrors('file_url');
            $this->assertSame(0, ChallengesFiles::where('challenge_id', $challenge->id)->count());
        });
    }






    // -----------------------------------------------------------
    // otherSchemes
    // -----------------------------------------------------------
    //
    // Links of schemes other than http and https that the url
    // rule lets through.
    //
    // Used by:
    //   - test_lab_09_only_web_links_are_accepted_as_files
    //     (above)
    // -----------------------------------------------------------

    public static function otherSchemes(): array
    {
        return [
            'ftp'  => ['ftp://files.test/dump.zip'],
            'smb'  => ['smb://files.test/share/dump.zip'],
            'file' => ['file://files.test/etc/passwd'],
        ];
    }






    // -----------------------------------------------------------
    // test_lab_10_an_incomplete_challenge_form_is_refused
    // -----------------------------------------------------------
    //
    // Known bug LAB-10 (low): a challenge form missing a
    // required field ends in a server error instead of coming
    // back with errors. Admins\ChallengesController::store
    // (line 245) builds a validator and never asks it, so the
    // insert fails on the NOT NULL columns. Fix: validate the
    // request.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_10_an_incomplete_challenge_form_is_refused(): void
    {
        $response = $this->signInAdmin($this->makeAdmin(true))
            ->from('/control-panel/challenges/create')
            ->post('/control-panel/challenges/store', ['name' => 'Half a challenge']);

        $this->assertDefectStillOpen('LAB-10', function () use ($response) {
            $response->assertRedirect('/control-panel/challenges/create');
            $response->assertSessionHasErrors(['partner_id', 'descr', 'content', 'answer', 'points']);
            $this->assertSame(0, Challenges::where('name', 'Half a challenge')->count());
        });
    }






    // -----------------------------------------------------------
    // test_lab_29_a_lab_admins_challenge_carries_no_script
    // -----------------------------------------------------------
    //
    // Known bug LAB-29 (medium): a lab admin can run
    // JavaScript in the browser of every player who opens its
    // challenges. The challenge form stores the content as
    // given, the challenge page prints it as raw HTML
    // (users/challenge.blade.php), and the site's CSP allows
    // inline scripts; and as one session serves the player
    // pages and the control panel, the script also acts with
    // admin rights in the browser of a main admin signed into
    // both. Fix: keep the formatting the editor produces and
    // strip scripts and event handlers when saving — an HTML
    // sanitiser — at least for lab admins.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_29_a_lab_admins_challenge_carries_no_script(): void
    {
        $admin = $this->makeAdmin();
        $group = $this->makeGroup($admin);
        $player = $this->makeSsoPlayer();
        $this->joinGroup($group, $player);
        $this->signInAdmin($admin)->post('/control-panel/challenges/store', [
            'name'       => 'Scripted Task',
            'partner_id' => $this->makePartner()->id,
            'descr'      => 'A task.',
            'content'    => '<p>Formatting stays</p><script>alert(document.domain)</script><img src="x" onerror="alert(2)">',
            'answer'     => 'flag',
            'points'     => 10,
        ]);
        $challenge = Challenges::where('name', 'Scripted Task')->firstOrFail();
        $this->assignToGroup($group, $challenge);

        $page = $this->signIn($player)->get("/user/challenge/{$challenge->public_id}/{$group->public_id}");

        $this->assertDefectStillOpen('LAB-29', function () use ($page) {
            $page->assertOk();
            $page->assertSee('<p>Formatting stays</p>', false);
            $page->assertDontSee('<script>alert(document.domain)</script>', false);
            $page->assertDontSee('onerror=', false);
        });
    }
}
