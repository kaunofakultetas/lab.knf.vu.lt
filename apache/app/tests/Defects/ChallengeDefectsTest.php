<?php

// -----------------------------------------------------------
//  [*] ChallengeDefectsTest — open defects in challenge admin
//
//  Each test states the behaviour the lab wants where the
//  control panel's challenge pages fall short today, under a
//  LAB-nn id; KnownDefects reports it incomplete while the
//  defect is open and fails it once a fix lands. The banner
//  of every test gives the severity, the mechanism and where
//  it lives, and the fix.
//
//  Used by:
//    - PHPUnit — the Defects suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Defects;

use App\Models\Challenges;
use App\Models\ChallengesFiles;
use Tests\Support\KnownDefects;
use Tests\TestCase;








// -----------------------------------------------------------
// ChallengeDefectsTest
// -----------------------------------------------------------
//
// LAB-06 and LAB-29.
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
