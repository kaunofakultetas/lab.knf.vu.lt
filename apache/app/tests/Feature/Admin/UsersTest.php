<?php

// -----------------------------------------------------------
//  [*] UsersTest — player accounts in the control panel
//
//  Every admin may list and search the player accounts and
//  create local ones, which are active at once and may have
//  their login mailed to them in plain text. Only a main
//  admin may set a new password, switch an account active or
//  inactive, or delete one; accounts are created in a
//  dialog.
//  Deleting a VU account also removes its group
//  memberships.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature\Admin;

use App\Mail\SendNewPass;
use App\Models\ChallengesHistory;
use App\Models\ChallengesSubmitted;
use App\Models\GroupsUsers;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;








// -----------------------------------------------------------
// UsersTest
// -----------------------------------------------------------
//
// Mail is faked in every test; the database starts without
// player accounts.
//
// Used by:
//   - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------

final class UsersTest extends TestCase
{

    // The refusal the perms middleware flashes
    private const REFUSAL = "You don't have permissions to do this!";






    // -----------------------------------------------------------
    // setUp
    // -----------------------------------------------------------
    //
    // Fakes the mailer.
    //
    // Used by:
    //   - PHPUnit, before every test
    // -----------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }






    // -----------------------------------------------------------
    // test_the_list_shows_accounts_newest_first
    // -----------------------------------------------------------
    //
    // Each account shows its username, email, last login and
    // an active or inactive badge that posts the status
    // switch; a main admin also gets edit and delete actions.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_list_shows_accounts_newest_first(): void
    {
        $older = $this->makePlayer(['username' => 'OlderPlayer', 'last_login' => '2026-02-03 04:05:06']);
        $newer = $this->makePlayer(['username' => 'NewerPlayer', 'status' => 0]);

        $response = $this->signInAdmin($this->makeAdmin(true))->get('/control-panel/users');

        $response->assertOk();
        $response->assertViewIs('admins.users');
        $response->assertSeeInOrder(['<td>NewerPlayer</td>', 'Not active', '<td>OlderPlayer</td>', '2026-02-03 04:05:06', 'Active'], false);
        $response->assertSee("onClick=\"Post_Link('/control-panel/users/status/{$newer->id}/0');\"", false);
        $response->assertSee("onClick=\"Post_Link('/control-panel/users/status/{$older->id}/1');\"", false);
        $response->assertSee("users_form('edit', {$older->id})", false);
        $response->assertSee("/control-panel/users/delete/{$older->id}", false);
    }






    // -----------------------------------------------------------
    // test_lab_admins_get_the_list_without_edit_actions
    // -----------------------------------------------------------
    //
    // A lab admin sees the accounts and their status, but no
    // edit or delete actions and no status switch, which would
    // all refuse it.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_admins_get_the_list_without_edit_actions(): void
    {
        $player = $this->makePlayer(['username' => 'SomePlayer']);

        $response = $this->signInAdmin($this->makeAdmin())->get('/control-panel/users');

        $response->assertSee('<td>SomePlayer</td>', false);
        $response->assertSee('Active');
        $response->assertDontSee("users_form('edit', {$player->id})", false);
        $response->assertDontSee("/control-panel/users/delete/{$player->id}", false);
        $response->assertDontSee("/control-panel/users/status/{$player->id}/", false);
    }






    // -----------------------------------------------------------
    // test_the_list_searches_usernames_and_emails
    // -----------------------------------------------------------
    //
    // The search term may match part of a username or of an
    // email address; a search matching nothing says so.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_list_searches_usernames_and_emails(): void
    {
        $this->makePlayer(['username' => 'needle_by_name']);
        $this->makePlayer(['username' => 'ByEmail', 'email' => 'someone.needle@players.test']);
        $this->makePlayer(['username' => 'Unrelated']);
        $this->signInAdmin($this->makeAdmin(true));

        $this->get('/control-panel/users?q=needle')
            ->assertSee('<td>needle_by_name</td>', false)
            ->assertSee('<td>ByEmail</td>', false)
            ->assertDontSee('<td>Unrelated</td>', false);
        $this->get('/control-panel/users?q=nothing-matches-this')->assertSee('No Users');
    }






    // -----------------------------------------------------------
    // test_the_new_account_dialog_offers_the_login_mail
    // -----------------------------------------------------------
    //
    // The dialog asks for username, email and password and
    // offers to mail the login.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_new_account_dialog_offers_the_login_mail(): void
    {
        $response = $this->signInAdmin($this->makeAdmin())->get('/control-panel/dialog/users/new');

        $response->assertOk();
        $response->assertSee('action="https://lab.knf.vu.lt/control-panel/dialog/users/store"', false);
        $response->assertSee('name="username"', false);
        $response->assertSee('name="email"', false);
        $response->assertSee('name="password"', false);
        $response->assertSee('name="notif" value="1"', false);
    }






    // -----------------------------------------------------------
    // test_an_admin_creates_an_active_account
    // -----------------------------------------------------------
    //
    // The dialog creates an active account with the given
    // username and a hashed password, and returns to the list
    // with a message — for lab admins too.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_admin_creates_an_active_account(): void
    {
        $response = $this->signInAdmin($this->makeAdmin())->post('/control-panel/dialog/users/store', [
            'username' => 'NewPlayer',
            'email'    => 'new@players.test',
            'password' => 'given-password-1',
        ]);

        $response->assertRedirect('/control-panel/users');
        $response->assertSessionHas('success', 'User created!');
        $player = User::where('email', 'new@players.test')->firstOrFail();
        $this->assertSame('NewPlayer', $player->username);
        $this->assertSame(1, $player->status);
        $this->assertTrue(Hash::check('given-password-1', $player->password));
        Mail::assertNothingSent();
    }






    // -----------------------------------------------------------
    // test_the_login_is_mailed_on_request
    // -----------------------------------------------------------
    //
    // With the mail box ticked, the new account's address
    // gets its email and password.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_login_is_mailed_on_request(): void
    {
        $this->signInAdmin($this->makeAdmin(true))->post('/control-panel/dialog/users/store', [
            'username' => 'NewPlayer',
            'email'    => 'new@players.test',
            'password' => 'given-password-1',
            'notif'    => 1,
        ]);

        Mail::assertSent(SendNewPass::class, function (SendNewPass $mail) {
            return $mail->hasTo('new@players.test')
                && $mail->data['email'] === 'new@players.test'
                && $mail->data['new_pass'] === 'given-password-1';
        });
    }






    // -----------------------------------------------------------
    // test_taken_emails_and_usernames_are_refused
    // -----------------------------------------------------------
    //
    // An email or a username already in use is refused with a
    // message naming which, and no account is added.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_taken_emails_and_usernames_are_refused(): void
    {
        $this->makePlayer(['username' => 'Taken', 'email' => 'taken@players.test']);
        $this->signInAdmin($this->makeAdmin(true));

        $this->post('/control-panel/dialog/users/store', ['username' => 'Other', 'email' => 'taken@players.test', 'password' => 'p-1234567'])
            ->assertRedirect('/control-panel/users');
        $this->assertErrorFlashed('Email already registered!');
        $this->post('/control-panel/dialog/users/store', ['username' => 'Taken', 'email' => 'other@players.test', 'password' => 'p-1234567'])
            ->assertRedirect('/control-panel/users');
        $this->assertErrorFlashed('Username already registered!');

        $this->assertSame(1, User::count());
    }






    // -----------------------------------------------------------
    // test_the_edit_dialog_shows_whose_password_is_set
    // -----------------------------------------------------------
    //
    // The edit dialog names the account by username and email
    // — neither can be changed — and asks only for a new
    // password, posting the account's id.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_edit_dialog_shows_whose_password_is_set(): void
    {
        $player = $this->makePlayer(['username' => 'SomePlayer']);

        $response = $this->signInAdmin($this->makeAdmin(true))->get("/control-panel/dialog/users/edit/{$player->id}");

        $response->assertSee('action="https://lab.knf.vu.lt/control-panel/dialog/users/update"', false);
        $response->assertSee('SomePlayer');
        $response->assertSee($player->email);
        $response->assertDontSee('name="username"', false);
        $response->assertSee("name=\"uid\" type=\"hidden\" value=\"{$player->id}\"", false);
    }






    // -----------------------------------------------------------
    // test_a_main_admin_sets_a_new_password
    // -----------------------------------------------------------
    //
    // The new password replaces the old one, and is mailed
    // when asked for.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_main_admin_sets_a_new_password(): void
    {
        $player = $this->makePlayer();

        $response = $this->signInAdmin($this->makeAdmin(true))
            ->post('/control-panel/dialog/users/update', ['uid' => $player->id, 'password' => 'brand-new-pass', 'notif' => 1]);

        $response->assertRedirect('/control-panel/users');
        $response->assertSessionHas('success', 'Updated!');
        $this->assertTrue(Hash::check('brand-new-pass', $player->refresh()->password));
        Mail::assertSent(SendNewPass::class, function (SendNewPass $mail) use ($player) {
            return $mail->hasTo($player->email) && $mail->data['new_pass'] === 'brand-new-pass';
        });
    }






    // -----------------------------------------------------------
    // test_the_status_switch_flips_an_account
    // -----------------------------------------------------------
    //
    // The badge's switch turns an active account inactive — it
    // can no longer sign in — and back.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_status_switch_flips_an_account(): void
    {
        $player = $this->makePlayer();
        $this->signInAdmin($this->makeAdmin(true));

        $this->post("/control-panel/users/status/{$player->id}/1")->assertRedirect('/control-panel/users');
        $this->assertSame(0, $player->refresh()->status);
        $this->assertSame('Status changed!', session('success'));

        $this->post("/control-panel/users/status/{$player->id}/0");
        $this->assertSame(1, $player->refresh()->status);
    }






    // -----------------------------------------------------------
    // test_lab_admins_cannot_change_passwords_status_or_delete
    // -----------------------------------------------------------
    //
    // Setting a password, switching the status and deleting
    // send a lab admin back with the permissions error, and
    // the account stays as it was.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_admins_cannot_change_passwords_status_or_delete(): void
    {
        $player = $this->makePlayer();
        $hash = $player->password;
        $this->signInAdmin($this->makeAdmin())->from('/control-panel/users');

        $this->post('/control-panel/dialog/users/update', ['uid' => $player->id, 'password' => 'taken-over-1'])
            ->assertRedirect('/control-panel/users');
        $this->assertErrorFlashed(self::REFUSAL);
        $this->post("/control-panel/users/status/{$player->id}/1")->assertRedirect('/control-panel/users');
        $this->assertErrorFlashed(self::REFUSAL);
        $this->post("/control-panel/users/delete/{$player->id}")->assertRedirect('/control-panel/users');
        $this->assertErrorFlashed(self::REFUSAL);

        $player->refresh();
        $this->assertSame($hash, $player->password);
        $this->assertSame(1, $player->status);
    }






    // -----------------------------------------------------------
    // test_deleting_an_account_ends_on_the_user_list
    // -----------------------------------------------------------
    //
    // Deleting a VU account goes back to the accounts page with
    // "User deleted!", and takes the account's group memberships,
    // which are kept by VU id, with it.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_deleting_an_account_ends_on_the_user_list(): void
    {
        $player = $this->makeSsoPlayer();
        $group = $this->makeGroup();
        $this->joinGroup($group, $player);

        $response = $this->signInAdmin($this->makeAdmin(true))->post("/control-panel/users/delete/{$player->id}");

        $response->assertRedirect('/control-panel/users');
        $response->assertSessionHas('success', 'User deleted!');
        $this->assertNull(User::find($player->id));
        $this->assertSame(0, GroupsUsers::where('group_id', $group->id)->count());
    }






    // -----------------------------------------------------------
    // test_an_empty_account_form_is_refused
    // -----------------------------------------------------------
    //
    // The new-account dialog sent empty goes back with errors and
    // creates no account.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_empty_account_form_is_refused(): void
    {
        $before = User::count();

        $response = $this->signInAdmin($this->makeAdmin(true))->from('/control-panel/users')->post('/control-panel/dialog/users/store', []);

        $response->assertRedirect('/control-panel/users');
        $response->assertSessionHasErrors(['username', 'email', 'password']);
        $this->assertSame($before, User::count());
    }






    // -----------------------------------------------------------
    // test_a_new_password_must_be_usable
    // -----------------------------------------------------------
    //
    // Setting a password that is empty or shorter than the 8
    // characters the login form asks for is refused with the
    // error, and the old password keeps working.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_new_password_must_be_usable(): void
    {
        $player = $this->makePlayer();
        $this->signInAdmin($this->makeAdmin(true))->from('/control-panel/users');

        $this->post('/control-panel/dialog/users/update', ['uid' => $player->id, 'password' => ''])->assertSessionHasErrors('password');
        $this->post('/control-panel/dialog/users/update', ['uid' => $player->id, 'password' => 'short'])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check(self::PASSWORD, $player->refresh()->password));
    }






    // -----------------------------------------------------------
    // test_a_new_account_needs_a_usable_login
    // -----------------------------------------------------------
    //
    // An account the dialog creates needs an e-mail address
    // and a password of at least 8 characters, as the login
    // form does; otherwise nothing is created.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_new_account_needs_a_usable_login(): void
    {
        $this->signInAdmin($this->makeAdmin())->from('/control-panel/users');

        $this->post('/control-panel/dialog/users/store', ['username' => 'Shorty', 'email' => 'shorty@players.test', 'password' => 'short'])
            ->assertSessionHasErrors('password');
        $this->post('/control-panel/dialog/users/store', ['username' => 'Nomail', 'email' => 'not-an-address', 'password' => 'long-enough-1'])
            ->assertSessionHasErrors('email');

        $this->assertSame(0, User::whereIn('username', ['Shorty', 'Nomail'])->count());
    }






    // -----------------------------------------------------------
    // test_an_unknown_account_is_404
    // -----------------------------------------------------------
    //
    // The edit dialog of an account that does not exist
    // answers 404 instead of a server error.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_unknown_account_is_404(): void
    {
        $this->signInAdmin($this->makeAdmin(true))->get('/control-panel/dialog/users/edit/987654321')->assertNotFound();
    }






    // -----------------------------------------------------------
    // test_the_status_switch_takes_only_0_or_1
    // -----------------------------------------------------------
    //
    // A hand-made status other than 0 or 1 answers 404 instead
    // of a server error, and the account keeps its status.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_status_switch_takes_only_0_or_1(): void
    {
        $player = $this->makePlayer();
        $this->signInAdmin($this->makeAdmin(true));

        $this->post("/control-panel/users/status/{$player->id}/7")->assertNotFound();

        $this->assertSame(1, $player->refresh()->status);
    }






    // -----------------------------------------------------------
    // test_an_admin_switch_ends_a_pending_verification
    // -----------------------------------------------------------
    //
    // Switching an account never verified drops its pending
    // verification, so its link can no longer switch it back
    // on after an admin switched it off.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_admin_switch_ends_a_pending_verification(): void
    {
        $token = Str::random(50) . '-' . now()->getTimestamp();
        $player = $this->makePlayer(['status' => 0, 'verify_token' => hash('sha256', $token)]);
        $this->signInAdmin($this->makeAdmin(true));

        $this->post("/control-panel/users/status/{$player->id}/0");
        $this->post("/control-panel/users/status/{$player->id}/1");

        $this->assertSame('', $player->refresh()->verify_token);
        $this->app['auth']->guard('admins')->logout();
        $this->get("/verify/{$token}")->assertNotFound();
        $this->assertSame(0, $player->refresh()->status);
    }






    // -----------------------------------------------------------
    // test_deleting_an_account_takes_its_solves_along
    // -----------------------------------------------------------
    //
    // The account's solves and its history rows go with it, and
    // the shared history in the players' sidebar no longer
    // lists them.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_deleting_an_account_takes_its_solves_along(): void
    {
        $player = $this->makePlayer(['username' => 'Departed']);
        $challenge = $this->makeChallenge();
        $this->signIn($player)->post("/user/challenge/{$challenge->public_id}", ['answer' => $challenge->answer]);
        $this->assertStringContainsString('Departed', json_encode(ChallengesHistory::feed()));

        $this->signInAdmin($this->makeAdmin(true))->post("/control-panel/users/delete/{$player->id}");

        $this->assertSame(0, ChallengesSubmitted::where('user_id', $player->id)->count());
        $this->assertSame(0, ChallengesHistory::where('user_id', $player->id)->count());
        $this->assertStringNotContainsString('Departed', json_encode(ChallengesHistory::feed()));
    }






    // -----------------------------------------------------------
    // test_a_search_for_0_is_a_search
    // -----------------------------------------------------------
    //
    // The search term 0 narrows the accounts like any other
    // term, rather than counting as no search at all.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_search_for_0_is_a_search(): void
    {
        $this->makePlayer(['username' => 'agent007', 'email' => 'bond@players.test']);
        $this->makePlayer(['username' => 'Alice', 'email' => 'alice@players.test']);

        $response = $this->signInAdmin($this->makeAdmin(true))->get('/control-panel/users?q=0');

        $response->assertSee('<td>agent007</td>', false);
        $response->assertDontSee('<td>Alice</td>', false);
    }






    // -----------------------------------------------------------
    // test_a_password_for_a_deleted_account_says_so
    // -----------------------------------------------------------
    //
    // A password saved for an account another admin deleted in
    // the meantime goes back with "No user!", mails nothing and
    // does not claim the update worked.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_password_for_a_deleted_account_says_so(): void
    {
        $player = $this->makePlayer();
        $player->delete();

        $response = $this->signInAdmin($this->makeAdmin(true))
            ->from('/control-panel/users')
            ->post('/control-panel/dialog/users/update', ['uid' => $player->id, 'password' => 'brand-new-pass', 'notif' => 1]);

        $response->assertRedirect('/control-panel/users');
        $this->assertErrorFlashed('No user!');
        $response->assertSessionMissing('success');
        Mail::assertNothingSent();
    }






    // -----------------------------------------------------------
    // test_account_actions_end_on_the_accounts_page
    // -----------------------------------------------------------
    //
    // The status switch and the deletion lead back to the
    // accounts page, whatever page an earlier visit left asked
    // for in the session.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_account_actions_end_on_the_accounts_page(): void
    {
        $player = $this->makePlayer();
        $stale = ['url.intended' => 'https://lab.knf.vu.lt/control-panel/users/vu-groups'];
        $this->signInAdmin($this->makeAdmin(true));

        $this->withSession($stale)->post("/control-panel/users/status/{$player->id}/1")->assertRedirect('/control-panel/users');
        $this->withSession($stale)->post("/control-panel/users/delete/{$player->id}")->assertRedirect('/control-panel/users');
    }






    // -----------------------------------------------------------
    // test_deleting_an_account_takes_its_votes_back
    // -----------------------------------------------------------
    //
    // The deleted accounts' rows in cyber_challenges_votes go,
    // so their votes come off the challenge's thumbs counts,
    // whichever way each was cast; the vote of a player who
    // stays is left as it was.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_deleting_an_account_takes_its_votes_back(): void
    {
        $challenge = $this->makeChallenge();
        $up = $this->makePlayer();
        $down = $this->makePlayer();
        $stays = $this->makePlayer();
        $this->signIn($up)->post("/user/challenge/vote/up/{$challenge->public_id}");
        $this->signIn($down)->post("/user/challenge/vote/down/{$challenge->public_id}");
        $this->signIn($stays)->post("/user/challenge/vote/up/{$challenge->public_id}");

        $this->signInAdmin($this->makeAdmin(true));
        $this->post("/control-panel/users/delete/{$up->id}");
        $this->post("/control-panel/users/delete/{$down->id}");

        $this->assertSame([$stays->id => 'up'], DB::table('cyber_challenges_votes')->pluck('vote', 'user_id')->all());
        $this->signIn($stays)->get("/user/challenge/{$challenge->public_id}")
            ->assertSee('<span id="thumbs-up-count">1</span>', false)
            ->assertSee('<span id="thumbs-down-count">0</span>', false);
    }






    // -----------------------------------------------------------
    // test_an_account_field_in_the_address_never_replaces_the_forms
    // -----------------------------------------------------------
    //
    // An e-mail address put into the create form's address is
    // passed over: the account is checked and created with the
    // form's own.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_account_field_in_the_address_never_replaces_the_forms(): void
    {
        $this->signInAdmin($this->makeAdmin(true))->post('/control-panel/dialog/users/store?email=hijacked@players.test', [
            'username' => 'Newcomer',
            'email'    => 'newcomer@players.test',
            'password' => 'p-1234567',
        ]);

        $this->assertSame('newcomer@players.test', User::where('username', 'Newcomer')->value('email'));
        $this->assertFalse(User::where('email', 'hijacked@players.test')->exists());
    }






    // -----------------------------------------------------------
    // test_a_vu_account_sharing_a_local_accounts_email_gets_no_password
    // -----------------------------------------------------------
    //
    // The login form signs in the first account with the
    // e-mail, so a password set on a VU account whose e-mail
    // an older local account also holds would never work: the
    // panel refuses it, says why, and mails nothing.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_vu_account_sharing_a_local_accounts_email_gets_no_password(): void
    {
        Mail::fake();
        $this->makePlayer(['email' => 'jonas@stud.vu.lt']);
        $vu = $this->makeSsoPlayer(['email' => 'jonas@stud.vu.lt', 'password' => '']);

        $this->signInAdmin($this->makeAdmin(true))
            ->from('/control-panel/users')
            ->post('/control-panel/dialog/users/update', ['uid' => $vu->id, 'password' => 'new-pass-123', 'notif' => 1]);

        $this->assertErrorFlashed('This e-mail also belongs to a local account, which the login form signs into');
        Mail::assertNothingSent();
        $this->assertSame('', $vu->refresh()->password);
    }






    // -----------------------------------------------------------
    // test_a_vu_account_with_an_email_of_its_own_gets_its_password
    // -----------------------------------------------------------
    //
    // A VU account whose e-mail no other account holds still
    // gets the password typed, mailed with notif, and the
    // login form signs in with it.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_vu_account_with_an_email_of_its_own_gets_its_password(): void
    {
        Mail::fake();
        $vu = $this->makeSsoPlayer(['password' => '']);

        $this->signInAdmin($this->makeAdmin(true))->post('/control-panel/dialog/users/update', ['uid' => $vu->id, 'password' => 'new-pass-123', 'notif' => 1]);

        Mail::assertSent(SendNewPass::class);
        $this->assertTrue(Auth::guard('users')->attempt(['email' => $vu->email, 'password' => 'new-pass-123']));
    }
}
