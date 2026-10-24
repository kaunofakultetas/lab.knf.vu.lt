<?php

// -----------------------------------------------------------
//  [*] AdminDefectsTest — open defects of the control panel
//
//  Each test states the behaviour the lab wants where the
//  control panel falls short today, under a LAB-nn id;
//  KnownDefects reports it incomplete while the defect is
//  open and fails it once a fix lands. The banner of every
//  test gives the severity, the mechanism, naming its files
//  and methods, and the fix.
//
//  Used by:
//    - PHPUnit — the Defects suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Defects;

use App\Models\Challenges;
use App\Models\ChallengesFiles;
use App\Models\Pages;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Tests\Support\KnownDefects;
use Tests\TestCase;








// -----------------------------------------------------------
// AdminDefectsTest
// -----------------------------------------------------------
//
// LAB-56, LAB-58, LAB-63 to LAB-69. The race test runs the
// other request at the moment it would land live, from a
// DB::listen hook, as the Feature suite's race tests do.
//
// Used by:
//   - PHPUnit — the Defects suite (runTests.sh)
// -----------------------------------------------------------

final class AdminDefectsTest extends TestCase
{

    // assertDefectStillOpen, which reports a test incomplete
    // while its defect is open
    use KnownDefects;






    // -----------------------------------------------------------
    // test_lab_56_an_id_with_a_leading_zero_reaches_no_script
    // -----------------------------------------------------------
    //
    // Known bug LAB-56 (low): the digits-only id patterns
    // (RouteServiceProvider::boot) let a leading zero through.
    // MySQL reads 010 as 10, so the members, group challenges
    // and file links pages (UsersGroupsController's users_list
    // and challenges_list,
    // Admins\ChallengesController::file_list) show record 10
    // and pass r_exists for it, but they print the address's
    // text into their add buttons' inline onClick, where
    // JavaScript reads 010 as the octal 8: the dialog opens
    // for, and saves to, record 8. A link sent to an admin can
    // so land a member, a challenge or a file link on another
    // group or challenge than the page shows. Fix: let the
    // patterns refuse a leading zero, or print the id as a
    // number.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_56_an_id_with_a_leading_zero_reaches_no_script(): void
    {
        $group = $this->makeGroup();
        $challenge = $this->makeChallenge();
        $this->signInAdmin($this->makeAdmin(true));

        $bodies = [];
        foreach ([
            "/control-panel/users/vu-groups/users_list/0{$group->id}"      => "users_togroups_form('new', 0{$group->id})",
            "/control-panel/users/vu-groups/challenges_list/0{$group->id}" => "users_groupschallenges_form('new', 0{$group->id})",
            "/control-panel/challenges/files/0{$challenge->id}"            => "challenge_files_form('new', 0{$challenge->id})",
        ] as $page => $script) {
            $bodies[$page] = [$this->get($page)->getContent(), $script];
        }

        $this->assertDefectStillOpen('LAB-56', function () use ($bodies) {
            foreach ($bodies as $page => [$body, $script]) {
                $this->assertStringNotContainsString($script, $body, $page);
            }
        });
    }






    // -----------------------------------------------------------
    // test_lab_58_a_deleted_records_page_sends_the_admin_elsewhere
    // -----------------------------------------------------------
    //
    // Known bug LAB-58 (low): R_Exists refuses a missing group
    // or challenge with back(), also on the GET pages it
    // guards. The page an add or an update goes back to after
    // "No group!" or "No challenge!" — users_list,
    // challenges_list, a challenge's edit form or its file
    // links — is the refused one itself, and the browser keeps
    // sending that page as the referrer across the redirects,
    // so back() leads to it again and again until the browser
    // stops at too many redirects; the message never shows. It
    // takes a group or challenge another admin deleted while
    // the page was open. Fix: send a refused GET to the list
    // it belongs to.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_58_a_deleted_records_page_sends_the_admin_elsewhere(): void
    {
        $group = $this->makeGroup();
        $page = "/control-panel/users/vu-groups/users_list/{$group->id}";
        $this->signInAdmin($this->makeAdmin(true))->post("/control-panel/users/vu-groups/delete/{$group->id}");

        $response = $this->from($page)->get($page);

        $this->assertDefectStillOpen('LAB-58', function () use ($response, $page) {
            $this->assertNotSame(url($page), $response->headers->get('Location'), 'the page sends the admin back to itself');
        });
    }






    // -----------------------------------------------------------
    // test_lab_63_challenge_points_are_a_whole_number_in_range
    // -----------------------------------------------------------
    //
    // Known bug LAB-63 (low): Admins\ChallengesController's
    // store and update hold points to required alone, and the
    // form's number field has no bounds. MySQL, running
    // non-strict, stores a word as 0, keeps a negative number
    // — every solve then takes points off — and clips
    // 3000000000 to 2147483647, which a solve then adds to a
    // total clipped the same way, so deleting the challenge
    // later leaves the player with nothing of the points they
    // had. Lab admins create challenges too. Fix: require a
    // whole number from 0 to a sane maximum.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_63_challenge_points_are_a_whole_number_in_range(): void
    {
        $partner = $this->makePartner();
        $this->signInAdmin($this->makeAdmin(true))->from('/control-panel/challenges/create');

        foreach (['-100', 'abc', '3000000000'] as $n => $points) {
            $this->post('/control-panel/challenges/store', [
                'name'       => "Odd points {$n}",
                'partner_id' => $partner->id,
                'descr'      => 'Find the flag.',
                'content'    => '<p>Look closer.</p>',
                'answer'     => 'flag',
                'points'     => $points,
            ]);
        }

        $this->assertDefectStillOpen('LAB-63', function () {
            $this->assertSame(0, Challenges::where('name', 'like', 'Odd points %')->count());
        });
    }






    // -----------------------------------------------------------
    // test_lab_64_a_lab_admin_changes_no_link_created_meanwhile
    // -----------------------------------------------------------
    //
    // Known bug LAB-64 (low): R_Exists lets a membership, a
    // group challenge or a file link through when no row has
    // the id (membership_owned, group_challenge_owned,
    // file_owned and file_owned_uid), as removing a row
    // already gone changes nothing. But the action reads the
    // id again a few queries later: when another admin creates
    // a row with that id in between, the action changes or
    // deletes a row the lab admin does not own. Ids are handed
    // out in order and requests have no limit by design, so a
    // lab admin who keeps sending the next id can, say, point
    // another admin's public challenge's file link at a page
    // of their own. Here the link is created right after the
    // check. Fix: refuse a missing row in R_Exists, or check
    // ownership again where the action changes the row.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_64_a_lab_admin_changes_no_link_created_meanwhile(): void
    {
        $challenge = $this->makeChallenge(['created_by' => $this->makeAdmin(true)->id]);
        $id = (int) ChallengesFiles::max('id') + 1000;
        $this->signInAdmin($this->makeAdmin())->from('/control-panel/challenges');
        $interleaved = false;
        DB::listen(function (QueryExecuted $query) use ($challenge, $id, &$interleaved) {
            if (!$interleaved && str_starts_with($query->sql, 'select `challenge_id` from `cyber_challenges_files`')) {
                $interleaved = true;
                ChallengesFiles::insert(['id' => $id, 'challenge_id' => $challenge->id, 'name' => 'capture.pcap', 'file_url' => 'https://files.test/real']);
            }
        });

        $this->post('/control-panel/challenges_file/update', ['uid' => $id, 'name' => 'capture.pcap', 'file_url' => 'https://elsewhere.test/fake']);

        $this->assertTrue($interleaved);
        $this->assertDefectStillOpen('LAB-64', function () use ($id) {
            $this->assertSame('https://files.test/real', ChallengesFiles::whereKey($id)->value('file_url'));
        });
    }






    // -----------------------------------------------------------
    // test_lab_65_the_lists_page_with_the_panels_own_markup
    // -----------------------------------------------------------
    //
    // Known bug LAB-65 (low): the panel's paged lists print
    // $list->links(), whose markup Laravel 10 draws for
    // Tailwind by default, and AppServiceProvider::boot never
    // switches it to Bootstrap, the only styling the panel
    // loads. Past 50 rows — the accounts list on the live site
    // is well past — the list ends in arrows stretched to the
    // width of the table, a second set of Previous and Next
    // links and a "Showing 1 to 50 of" line. Fix:
    // Paginator::useBootstrapFive() in
    // AppServiceProvider::boot.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_65_the_lists_page_with_the_panels_own_markup(): void
    {
        foreach (range(1, 51) as $n) {
            $this->makeChallenge();
        }

        $response = $this->signInAdmin($this->makeAdmin(true))->get('/control-panel/challenges');

        $this->assertDefectStillOpen('LAB-65', function () use ($response) {
            $response->assertSee('class="pagination"', false);
        });
    }






    // -----------------------------------------------------------
    // test_lab_66_a_page_past_the_end_shows_the_last_page
    // -----------------------------------------------------------
    //
    // Known bug LAB-66 (low): a paged list asked for a page
    // past its end (Admins\BaseController::listPage) gets an
    // empty page, and the list views print "No …" without any
    // page links for an empty page. That is where a deletion
    // of the last row of the last page goes back to, so the
    // admin sees "No Users", "No challenges" or "No Groups"
    // with nothing to lead back to the rest of the list. Fix:
    // show the last page for a page past the end.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_66_a_page_past_the_end_shows_the_last_page(): void
    {
        $this->makeGroup(null, ['name' => 'Networks Lab 1']);

        $response = $this->signInAdmin($this->makeAdmin(true))->followingRedirects()->get('/control-panel/users/vu-groups?page=2');

        $this->assertDefectStillOpen('LAB-66', function () use ($response) {
            $response->assertSee('Networks Lab 1');
        });
    }






    // -----------------------------------------------------------
    // test_lab_67_the_file_links_page_links_its_other_pages
    // -----------------------------------------------------------
    //
    // Known bug LAB-67 (low):
    // Admins\ChallengesController::file_list hands its view
    // the file links 50 to a page, but
    // admins/challenges_files.blade.php, unlike the other
    // lists, prints no page links, so a challenge's links past
    // the 50th cannot be seen, edited or deleted but by typing
    // the page into the address. Fix: print the paginator's
    // links under the table.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_67_the_file_links_page_links_its_other_pages(): void
    {
        $challenge = $this->makeChallenge();
        foreach (range(1, 51) as $n) {
            ChallengesFiles::create(['challenge_id' => $challenge->id, 'name' => "file{$n}.pcap", 'file_url' => "https://files.test/{$n}"]);
        }

        $response = $this->signInAdmin($this->makeAdmin(true))->get("/control-panel/challenges/files/{$challenge->id}");

        $this->assertDefectStillOpen('LAB-67', function () use ($response, $challenge) {
            $response->assertSee("files/{$challenge->id}?page=2", false);
        });
    }






    // -----------------------------------------------------------
    // test_lab_68_a_search_sent_as_a_list_shows_the_list
    // -----------------------------------------------------------
    //
    // Known bug LAB-68 (low): Admins\UsersController::index
    // and Admins\ChallengesController::index treat a search
    // that is not text as no search, but their views put
    // request()->query('q') itself back into the search box,
    // and the box's escaping fails on a list: an address with
    // q[]= ends in a 500. Only a hand-made address does this.
    // Fix: give the views the search the action settled on.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_68_a_search_sent_as_a_list_shows_the_list(): void
    {
        $this->signInAdmin($this->makeAdmin(true));

        $users = $this->get('/control-panel/users?q[]=x');
        $challenges = $this->get('/control-panel/challenges?q[]=x');

        $this->assertDefectStillOpen('LAB-68', function () use ($users, $challenges) {
            $users->assertOk();
            $challenges->assertOk();
        });
    }






    // -----------------------------------------------------------
    // test_lab_69_a_page_alias_is_one_piece_of_an_address
    // -----------------------------------------------------------
    //
    // Known bug LAB-69 (low): Admins\PagesController's store
    // and update hold a page's alias to required, 50
    // characters and unique, but the page opens at
    // /page/{alias}, whose alias stops at a slash, so a page
    // saved as rules/2026 is a 404 for good; a ? or # in the
    // alias sends visitors to the front page instead. Fix:
    // allow letters, digits, dashes and underscores only.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_69_a_page_alias_is_one_piece_of_an_address(): void
    {
        $this->signInAdmin($this->makeAdmin(true))->from('/control-panel/pages');

        $this->post('/control-panel/pages/store', ['name' => 'Rules', 'content' => '<p>The rules.</p>', 'alias' => 'rules/2026']);

        $this->assertDefectStillOpen('LAB-69', function () {
            $this->assertFalse(Pages::where('alias', 'rules/2026')->exists());
        });
    }
}
