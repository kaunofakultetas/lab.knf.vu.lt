<?php

// -----------------------------------------------------------
//  [*] AdminDefectsTest — open defects in the control panel
//
//  Each test states the behaviour the lab wants where the
//  control panel falls short today, under a LAB-nn id;
//  KnownDefects reports it incomplete while the defect is
//  open and fails it once a fix lands. The banner of every
//  test gives the severity, the mechanism with file and
//  line, and the fix.
//
//  Used by:
//    - PHPUnit — the Defects suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Defects;

use App\Models\Categories;
use App\Models\Emails;
use App\Models\Groups;
use App\Models\GroupsChallenges;
use App\Models\GroupsUsers;
use App\Models\Pages;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\KnownDefects;
use Tests\TestCase;








// -----------------------------------------------------------
// AdminDefectsTest
// -----------------------------------------------------------
//
// LAB-11 to LAB-14, LAB-19 and LAB-28.
//
// Used by:
//   - PHPUnit — the Defects suite (runTests.sh)
// -----------------------------------------------------------

final class AdminDefectsTest extends TestCase
{

    use KnownDefects;

    // The refusal the perms and r_exists middleware flash
    private const REFUSAL = "You don't have permissions to do this!";






    // -----------------------------------------------------------
    // test_lab_11_deleting_an_account_ends_on_the_user_list
    // -----------------------------------------------------------
    //
    // Known bug LAB-11 (medium): deleting a player account
    // ends in a server error. Admins\UsersController::destroy
    // (line 166) deletes the account, then clears its group
    // memberships by a user_id column cyber_users_groups does
    // not have — memberships are stored by vu_id. The account
    // is gone, its memberships stay, and the admin sees an
    // error page. Fix: delete the memberships by the
    // account's vu_id, when it has one.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_11_deleting_an_account_ends_on_the_user_list(): void
    {
        $player = $this->makeSsoPlayer();
        $group = $this->makeGroup();
        $this->joinGroup($group, $player);

        $response = $this->signInAdmin($this->makeAdmin(true))->get("/control-panel/users/delete/{$player->id}");

        $this->assertDefectStillOpen('LAB-11', function () use ($response, $player, $group) {
            $response->assertRedirect('/control-panel/users');
            $response->assertSessionHas('success', 'User deleted!');
            $this->assertNull(User::find($player->id));
            $this->assertSame(0, GroupsUsers::where('group_id', $group->id)->count(), 'the memberships stayed');
        });
    }






    // -----------------------------------------------------------
    // test_lab_12_lab_admins_cannot_create_or_rename_categories
    // -----------------------------------------------------------
    //
    // Known bug LAB-12 (medium): categories are main-only in
    // the list and on deletion, but the dialog routes that
    // create and rename them (routes/web.php, the
    // dialog/category routes) lack the perms middleware, so a
    // lab admin can add categories and rename the lab-wide
    // ones. Fix: add perms to the four dialog/category
    // routes.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_12_lab_admins_cannot_create_or_rename_categories(): void
    {
        $this->signInAdmin($this->makeAdmin())->from('/control-panel/dashboard');

        $this->post('/control-panel/dialog/category/store', ['name' => 'Lab Admin Category']);
        $created = session('errors') ? session('errors')->all() : [];
        $this->post('/control-panel/dialog/category/update', ['uid' => 1, 'name' => 'Renamed By A Lab Admin']);
        $renamed = session('errors') ? session('errors')->all() : [];

        $this->assertDefectStillOpen('LAB-12', function () use ($created, $renamed) {
            $this->assertContains(self::REFUSAL, $created);
            $this->assertContains(self::REFUSAL, $renamed);
            $this->assertSame(0, Categories::where('name', 'Lab Admin Category')->count());
            $this->assertSame('Cryptography', Categories::find(1)->name);
        });
    }






    // -----------------------------------------------------------
    // test_lab_13_lab_admins_cannot_empty_other_admins_groups
    // -----------------------------------------------------------
    //
    // Known bug LAB-13 (medium): any admin can remove members
    // and challenges from any group. The two removal routes
    // (routes/web.php, users/vu-groups/delete_group and
    // delete_challenge) run no ownership check, unlike every
    // other group route, and take bare row ids. Fix: check
    // that the row's group belongs to the admin, as r_exists
    // does for the group itself.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_13_lab_admins_cannot_empty_other_admins_groups(): void
    {
        $group = $this->makeGroup($this->makeAdmin());
        $membership = GroupsUsers::create(['group_id' => $group->id, 'vu_id' => 's1234567']);
        $place = $this->assignToGroup($group, $this->makeChallenge());
        $this->signInAdmin($this->makeAdmin())->from('/control-panel/users/vu-groups');

        $this->get("/control-panel/users/vu-groups/delete_group/{$membership->id}");
        $this->get("/control-panel/users/vu-groups/delete_challenge/{$place->id}");

        $this->assertDefectStillOpen('LAB-13', function () use ($membership, $place) {
            $this->assertNotNull(GroupsUsers::find($membership->id), 'the member was removed');
            $this->assertNotNull(GroupsChallenges::find($place->id), 'the challenge was removed');
        });
    }






    // -----------------------------------------------------------
    // test_lab_14_an_empty_form_is_refused_with_errors
    // -----------------------------------------------------------
    //
    // Known bug LAB-14 (low): several control-panel forms
    // store what they are given without validating it, and
    // an empty one ends in a server error when the insert
    // meets a NOT NULL column — the category and group
    // dialogs, the user dialog and the mail template form
    // (EmailsController::store builds a validator and never
    // asks it). Fix: validate each request.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    #[DataProvider('emptyForms')]
    public function test_lab_14_an_empty_form_is_refused_with_errors(string $route, string $model): void
    {
        $before = $model::count();

        $response = $this->signInAdmin($this->makeAdmin(true))->from('/control-panel/dashboard')->post($route, []);

        $this->assertDefectStillOpen('LAB-14', function () use ($response, $model, $before) {
            $response->assertRedirect('/control-panel/dashboard');
            $response->assertSessionHasErrors();
            $this->assertSame($before, $model::count());
        });
    }






    // -----------------------------------------------------------
    // emptyForms
    // -----------------------------------------------------------
    //
    // The forms that crash on empty input, with the model of
    // the table they write.
    //
    // Used by:
    //   - test_lab_14_an_empty_form_is_refused_with_errors
    //     (above)
    // -----------------------------------------------------------

    public static function emptyForms(): array
    {
        return [
            'category'      => ['/control-panel/dialog/category/store', Categories::class],
            'group'         => ['/control-panel/dialog/users/vu-groups/store', Groups::class],
            'user'          => ['/control-panel/dialog/users/store', User::class],
            'mail template' => ['/control-panel/emails/store', Emails::class],
        ];
    }






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






    // -----------------------------------------------------------
    // test_lab_28_a_demoted_admin_loses_the_main_pages_at_once
    // -----------------------------------------------------------
    //
    // Known bug LAB-28 (medium): taking main-admin rights away
    // from an account takes effect only when it signs in
    // again. The perms and r_exists middleware and the
    // challenge form read the main flag the SSO login copied
    // into the session (AdminAuthController::check_admin),
    // never the account, so a demoted admin keeps every
    // main-only page for as long as its session lives. Fix:
    // read the flag from the signed-in admin's row.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_28_a_demoted_admin_loses_the_main_pages_at_once(): void
    {
        $admin = $this->makeAdmin(true);
        $this->signInAdmin($admin)->from('/control-panel/dashboard');
        $admin->update(['main' => 0]);

        $response = $this->get('/control-panel/settings');

        $this->assertDefectStillOpen('LAB-28', function () use ($response) {
            $response->assertRedirect('/control-panel/dashboard');
            $this->assertErrorFlashed(self::REFUSAL);
        });
    }
}
