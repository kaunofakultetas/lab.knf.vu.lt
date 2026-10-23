<?php

// -----------------------------------------------------------
//  [*] ChallengeFilesTest — the file links of a challenge
//
//  A challenge's "files" are named links to wherever the
//  files are hosted; players see them as links on the
//  challenge page. Since the upload hardening, a link must be
//  an http or https address of at most 2048 characters with
//  a name of at most 255, so a javascript: link — the one
//  that would run in a player's browser — never reaches the
//  page. The list and the forms are reached from the admin
//  challenge list, and a link is edited and deleted by
//  whoever may change the challenge it belongs to.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature\Admin;

use App\Models\ChallengesFiles;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;








// -----------------------------------------------------------
// ChallengeFilesTest
// -----------------------------------------------------------
//
// A main admin works on challenges of the seeded main admin,
// unless a test says otherwise.
//
// Used by:
//   - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------

final class ChallengeFilesTest extends TestCase
{






    // -----------------------------------------------------------
    // test_the_file_list_names_the_challenge_and_its_links
    // -----------------------------------------------------------
    //
    // The list is titled with the challenge's name and shows
    // its links with edit and delete actions; another
    // challenge's links are not there.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_file_list_names_the_challenge_and_its_links(): void
    {
        $challenge = $this->makeChallenge(['name' => 'Packet Detective']);
        $file = ChallengesFiles::create(['challenge_id' => $challenge->id, 'name' => 'capture.pcap', 'file_url' => 'https://files.test/capture.pcap']);
        ChallengesFiles::create(['challenge_id' => $this->makeChallenge()->id, 'name' => 'other.zip', 'file_url' => 'https://files.test/other.zip']);

        $response = $this->signInAdmin($this->makeAdmin(true))->get("/control-panel/challenges/files/{$challenge->id}");

        $response->assertOk();
        $response->assertViewIs('admins.challenges_files');
        $response->assertSee('Packet Detective files');
        $response->assertSee('<td>capture.pcap</td>', false);
        $response->assertSee("challenge_files_form('edit', {$file->id})", false);
        $response->assertSee("/control-panel/challenges/files/delete/{$file->id}", false);
        $response->assertDontSee('other.zip');
    }






    // -----------------------------------------------------------
    // test_an_empty_file_list_says_so
    // -----------------------------------------------------------
    //
    // A challenge without links says it has none.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_empty_file_list_says_so(): void
    {
        $challenge = $this->makeChallenge();

        $this->signInAdmin($this->makeAdmin(true))
            ->get("/control-panel/challenges/files/{$challenge->id}")
            ->assertSee('No challenge files');
    }






    // -----------------------------------------------------------
    // test_the_new_link_dialog_posts_for_the_challenge
    // -----------------------------------------------------------
    //
    // The dialog for a new link posts to the store route with
    // the challenge's id.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_new_link_dialog_posts_for_the_challenge(): void
    {
        $challenge = $this->makeChallenge();

        $response = $this->signInAdmin($this->makeAdmin(true))->get("/control-panel/dialog/challenge_file/new/{$challenge->id}");

        $response->assertOk();
        $response->assertSee('action="https://lab.knf.vu.lt/control-panel/challenges_file/store"', false);
        $response->assertSee("name=\"uid\" type=\"hidden\" value=\"{$challenge->id}\"", false);
    }






    // -----------------------------------------------------------
    // test_the_edit_dialog_is_filled_with_the_link
    // -----------------------------------------------------------
    //
    // The dialog for an existing link carries its name and
    // address and posts to the update route with the link's
    // id.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_edit_dialog_is_filled_with_the_link(): void
    {
        $file = ChallengesFiles::create(['challenge_id' => $this->makeChallenge()->id, 'name' => 'capture.pcap', 'file_url' => 'https://files.test/capture.pcap']);

        $response = $this->signInAdmin($this->makeAdmin(true))->get("/control-panel/dialog/challenge_file/edit/{$file->id}");

        $response->assertOk();
        $response->assertSee('action="https://lab.knf.vu.lt/control-panel/challenges_file/update"', false);
        $response->assertSee('name="name" type="text" value="capture.pcap"', false);
        $response->assertSee('name="file_url" type="text" value="https://files.test/capture.pcap"', false);
        $response->assertSee("name=\"uid\" type=\"hidden\" value=\"{$file->id}\"", false);
    }






    // -----------------------------------------------------------
    // test_a_web_link_is_added_to_the_challenge
    // -----------------------------------------------------------
    //
    // A named https link is stored for the challenge, and the
    // admin returns to the list.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_web_link_is_added_to_the_challenge(): void
    {
        $challenge = $this->makeChallenge();

        $response = $this->signInAdmin($this->makeAdmin(true))
            ->from("/control-panel/challenges/files/{$challenge->id}")
            ->post('/control-panel/challenges_file/store', [
                'uid'      => $challenge->id,
                'name'     => 'capture.pcap',
                'file_url' => 'https://files.test/capture.pcap',
            ]);

        $response->assertRedirect("/control-panel/challenges/files/{$challenge->id}");
        $response->assertSessionHas('success', 'File created!');
        $this->assertDatabaseHas('cyber_challenges_files', [
            'challenge_id' => $challenge->id,
            'name'         => 'capture.pcap',
            'file_url'     => 'https://files.test/capture.pcap',
        ]);
    }






    // -----------------------------------------------------------
    // test_a_lab_admin_adds_links_to_its_own_challenge
    // -----------------------------------------------------------
    //
    // A lab admin may add links to a challenge it created.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_lab_admin_adds_links_to_its_own_challenge(): void
    {
        $admin = $this->makeAdmin();
        $challenge = $this->makeChallenge(['created_by' => $admin->id]);

        $this->signInAdmin($admin)->post('/control-panel/challenges_file/store', [
            'uid'      => $challenge->id,
            'name'     => 'notes.txt',
            'file_url' => 'http://files.test/notes.txt',
        ])->assertSessionHas('success', 'File created!');

        $this->assertSame(1, ChallengesFiles::where('challenge_id', $challenge->id)->count());
    }






    // -----------------------------------------------------------
    // test_a_link_can_be_edited_and_deleted
    // -----------------------------------------------------------
    //
    // The link dialog's changes are saved and the delete
    // button removes the link. The links get ids no challenge
    // has, so an ownership check that took them for challenge
    // ids would refuse both.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_link_can_be_edited_and_deleted(): void
    {
        $challenge = $this->makeChallenge();
        $edited = ChallengesFiles::forceCreate(['id' => 2000000001, 'challenge_id' => $challenge->id, 'name' => 'old.pcap', 'file_url' => 'https://files.test/old']);
        $deleted = ChallengesFiles::forceCreate(['id' => 2000000002, 'challenge_id' => $challenge->id, 'name' => 'gone.pcap', 'file_url' => 'https://files.test/gone']);
        $this->signInAdmin($this->makeAdmin(true))->from("/control-panel/challenges/files/{$challenge->id}");

        $this->post('/control-panel/challenges_file/update', ['uid' => $edited->id, 'name' => 'new.pcap', 'file_url' => 'https://files.test/new'])
            ->assertSessionHas('success', 'File updated!');
        $this->post("/control-panel/challenges/files/delete/{$deleted->id}")
            ->assertSessionHas('success', 'File deleted!');

        $this->assertSame('new.pcap', $edited->refresh()->name);
        $this->assertSame('https://files.test/new', $edited->file_url);
        $this->assertNull(ChallengesFiles::find($deleted->id));
    }






    // -----------------------------------------------------------
    // test_a_lab_admin_changes_only_the_links_of_its_own_challenges
    // -----------------------------------------------------------
    //
    // A lab admin edits and deletes the links of a challenge
    // it created, and is refused with the permissions message
    // on another admin's, which stay as they were.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_lab_admin_changes_only_the_links_of_its_own_challenges(): void
    {
        $admin = $this->makeAdmin();
        $own = ChallengesFiles::create(['challenge_id' => $this->makeChallenge(['created_by' => $admin->id])->id, 'name' => 'own.pcap', 'file_url' => 'https://files.test/own']);
        $other = ChallengesFiles::create(['challenge_id' => $this->makeChallenge()->id, 'name' => 'other.pcap', 'file_url' => 'https://files.test/other']);
        $this->signInAdmin($admin)->from('/control-panel/challenges');

        $this->post('/control-panel/challenges_file/update', ['uid' => $other->id, 'name' => 'taken.pcap', 'file_url' => 'https://files.test/taken']);
        $this->assertErrorFlashed("You don't have permissions to do this!");
        $this->post("/control-panel/challenges/files/delete/{$other->id}");
        $this->assertErrorFlashed("You don't have permissions to do this!");
        $this->assertSame('other.pcap', $other->refresh()->name);

        $this->post('/control-panel/challenges_file/update', ['uid' => $own->id, 'name' => 'renamed.pcap', 'file_url' => 'https://files.test/own'])
            ->assertSessionHas('success', 'File updated!');
        $this->assertSame('renamed.pcap', $own->refresh()->name);
        $this->post("/control-panel/challenges/files/delete/{$own->id}")
            ->assertSessionHas('success', 'File deleted!');
        $this->assertNull(ChallengesFiles::find($own->id));
    }






    // -----------------------------------------------------------
    // test_only_named_web_links_are_accepted
    // -----------------------------------------------------------
    //
    // A link that is no web address — javascript:, data:,
    // ftp:, smb: and file: links included — or is too long,
    // or comes without a usable name, is refused with an error
    // under the field, and nothing is stored.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    #[DataProvider('badLinks')]
    public function test_only_named_web_links_are_accepted(string $name, string $url, string $field): void
    {
        $challenge = $this->makeChallenge();

        $response = $this->signInAdmin($this->makeAdmin(true))->post('/control-panel/challenges_file/store', [
            'uid'      => $challenge->id,
            'name'     => $name,
            'file_url' => $url,
        ]);

        $response->assertSessionHasErrors($field);
        $this->assertSame(0, ChallengesFiles::where('challenge_id', $challenge->id)->count());
    }






    // -----------------------------------------------------------
    // badLinks
    // -----------------------------------------------------------
    //
    // Name, address and the field the error lands on.
    //
    // Used by:
    //   - test_only_named_web_links_are_accepted (above)
    // -----------------------------------------------------------

    public static function badLinks(): array
    {
        return [
            'javascript link'         => ['run.js', 'javascript:alert(document.cookie)', 'file_url'],
            'javascript with slashes' => ['run.js', 'javascript://files.test/%0Aalert(1)', 'file_url'],
            'data link'               => ['page.html', 'data:text/html,<script>alert(1)</script>', 'file_url'],
            'ftp link'                => ['dump.zip', 'ftp://files.test/dump.zip', 'file_url'],
            'smb link'                => ['dump.zip', 'smb://files.test/share/dump.zip', 'file_url'],
            'file link'               => ['passwd', 'file://files.test/etc/passwd', 'file_url'],
            'not a link'              => ['dump.zip', 'files.test/dump.zip', 'file_url'],
            'no link'                 => ['dump.zip', '', 'file_url'],
            'overlong link'           => ['dump.zip', 'https://files.test/' . str_repeat('a', 2048), 'file_url'],
            'no name'                 => ['', 'https://files.test/dump.zip', 'name'],
            'overlong name'           => [str_repeat('n', 256), 'https://files.test/dump.zip', 'name'],
        ];
    }
}
