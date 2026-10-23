<?php

// -----------------------------------------------------------
//  [*] AdminDefectsTest — open defects in the control panel
//
//  Each test states the behaviour the lab wants where the
//  control panel falls short today, under a LAB-nn id;
//  KnownDefects reports it incomplete while the defect is
//  open and fails it once a fix lands. The banner of every
//  test gives the severity, the mechanism and where it
//  lives, and the fix.
//
//  Used by:
//    - PHPUnit — the Defects suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Defects;

use App\Models\Emails;
use App\Models\GroupsUsers;
use App\Models\Pages;
use Illuminate\Support\Facades\Mail;
use Tests\Support\KnownDefects;
use Tests\TestCase;








// -----------------------------------------------------------
// AdminDefectsTest
// -----------------------------------------------------------
//
// LAB-19.
//
// Used by:
//   - PHPUnit — the Defects suite (runTests.sh)
// -----------------------------------------------------------

final class AdminDefectsTest extends TestCase
{

    use KnownDefects;






    // -----------------------------------------------------------
    // test_lab_19_a_plain_link_never_changes_anything
    // -----------------------------------------------------------
    //
    // Known bug LAB-19 (medium): every change the control
    // panel makes from a link — deleting a category, partner,
    // page, mail template, account, challenge or group,
    // removing a group member or challenge, switching an
    // account or a challenge, sending a template to the whole
    // list — is a plain GET request (routes/web.php). CSRF
    // tokens never guard GET requests, and the session cookie
    // (SameSite=Lax) still travels with a top-level link from
    // another site, so a link an admin clicks anywhere makes
    // the change in the admin's name. Fix: turn each action
    // into a POST form carrying the CSRF token; the home tests
    // drive the actions by GET today and move to POST with the
    // fix.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_19_a_plain_link_never_changes_anything(): void
    {
        Mail::fake();
        $_SERVER['MAILJET_REGISTERED_LIST'] = 'registered@lists.test';
        $category = $this->makeCategory('Kept Category');
        $partner = $this->makePartner();
        $page = Pages::create(['name' => 'Rules', 'content' => '<p>Rules</p>', 'alias' => 'rules']);
        $template = Emails::create(['name' => 'Kept', 'subject' => 'Kept', 'content' => '<p>Kept</p>', 'sent' => 0]);
        $unsent = Emails::create(['name' => 'Unsent', 'subject' => 'Unsent', 'content' => '<p>Unsent</p>', 'sent' => 0]);
        $account = $this->makePlayer();
        $active = $this->makePlayer();
        $challenge = $this->makeChallenge();
        $visible = $this->makeChallenge();
        $group = $this->makeGroup();
        $other = $this->makeGroup();
        $membership = GroupsUsers::create(['group_id' => $other->id, 'vu_id' => 's1234567']);
        $place = $this->assignToGroup($other, $this->makeChallenge());
        $links = [
            'delete a category'        => ["/control-panel/category/delete/{$category->id}", fn () => $category->fresh() !== null],
            'delete a partner'         => ["/control-panel/partners/delete/{$partner->id}", fn () => $partner->fresh() !== null],
            'delete a page'            => ["/control-panel/pages/delete/{$page->id}", fn () => $page->fresh() !== null],
            'delete a mail template'   => ["/control-panel/emails/delete/{$template->id}", fn () => $template->fresh() !== null],
            'send a template to all'   => ["/control-panel/emails/send/{$unsent->id}", fn () => (int) $unsent->fresh()->sent === 0],
            'delete an account'        => ["/control-panel/users/delete/{$account->id}", fn () => $account->fresh() !== null],
            'switch an account off'    => ["/control-panel/users/status/{$active->id}/1", fn () => $active->fresh()->status === 1],
            'delete a challenge'       => ["/control-panel/challenges/delete/{$challenge->id}", fn () => $challenge->fresh() !== null],
            'hide a challenge'         => ["/control-panel/challenges/status/1/{$visible->id}", fn () => $visible->fresh()->hidden === 0],
            'delete a group'           => ["/control-panel/users/vu-groups/delete/{$group->id}", fn () => $group->fresh() !== null],
            'remove a group member'    => ["/control-panel/users/vu-groups/delete_group/{$membership->id}", fn () => $membership->fresh() !== null],
            'remove a group challenge' => ["/control-panel/users/vu-groups/delete_challenge/{$place->id}", fn () => $place->fresh() !== null],
        ];
        $this->signInAdmin($this->makeAdmin(true))->from('/control-panel/dashboard');

        foreach ($links as [$link]) {
            $this->get($link);
        }
        unset($_SERVER['MAILJET_REGISTERED_LIST']);

        $this->assertDefectStillOpen('LAB-19', function () use ($links) {
            $changed = array_keys(array_filter($links, fn (array $link) => !$link[1]()));
            $this->assertSame([], $changed, 'plain links that changed data: ' . implode(', ', $changed));
        });
    }
}
