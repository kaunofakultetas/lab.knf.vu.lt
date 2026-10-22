<?php

// -----------------------------------------------------------
//  [*] UsersTest — player accounts in the control panel
//
//  Every admin may list and search the player accounts and
//  create local ones, which are active at once and may have
//  their login mailed to them in plain text. Only a main
//  admin may set a new password, switch an account active or
//  inactive, or delete one. Two routes create accounts — the
//  dialog's and an older plain one — and both are covered.
//  Deleting an account is broken today and pinned in
//  AdminDefectsTest; here only its refusal for lab admins.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature\Admin;

use App\Mail\SendNewPass;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
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
    // an active or inactive badge that links the status
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
        $response->assertSee("href=\"/control-panel/users/status/{$newer->id}/0\"", false);
        $response->assertSee("href=\"/control-panel/users/status/{$older->id}/1\"", false);
        $response->assertSee("users_form('edit', {$older->id})", false);
        $response->assertSee("/control-panel/users/delete/{$older->id}", false);
    }






    // -----------------------------------------------------------
    // test_lab_admins_get_the_list_without_edit_actions
    // -----------------------------------------------------------
    //
    // A lab admin sees the accounts but no edit or delete
    // actions.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_admins_get_the_list_without_edit_actions(): void
    {
        $player = $this->makePlayer(['username' => 'SomePlayer']);

        $response = $this->signInAdmin($this->makeAdmin())->get('/control-panel/users');

        $response->assertSee('<td>SomePlayer</td>', false);
        $response->assertDontSee("users_form('edit', {$player->id})", false);
        $response->assertDontSee("/control-panel/users/delete/{$player->id}", false);
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
    // Either route creates an active account with the given
    // username and a hashed password, and returns to the list
    // with a message — for lab admins too.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    #[DataProvider('storeRoutes')]
    public function test_an_admin_creates_an_active_account(string $route): void
    {
        $response = $this->signInAdmin($this->makeAdmin())->post($route, [
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
    // storeRoutes
    // -----------------------------------------------------------
    //
    // The two routes that create an account.
    //
    // Used by:
    //   - test_an_admin_creates_an_active_account (above)
    // -----------------------------------------------------------

    public static function storeRoutes(): array
    {
        return [
            'dialog route' => ['/control-panel/dialog/users/store'],
            'plain route'  => ['/control-panel/users/store'],
        ];
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
    // The badge link turns an active account inactive — it
    // can no longer sign in — and back.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_status_switch_flips_an_account(): void
    {
        $player = $this->makePlayer();
        $this->signInAdmin($this->makeAdmin(true));

        $this->get("/control-panel/users/status/{$player->id}/1")->assertRedirect('/control-panel/users');
        $this->assertSame(0, $player->refresh()->status);
        $this->assertSame('Status changed!', session('success'));

        $this->get("/control-panel/users/status/{$player->id}/0");
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
        $this->get("/control-panel/users/status/{$player->id}/1")->assertRedirect('/control-panel/users');
        $this->assertErrorFlashed(self::REFUSAL);
        $this->get("/control-panel/users/delete/{$player->id}")->assertRedirect('/control-panel/users');
        $this->assertErrorFlashed(self::REFUSAL);

        $player->refresh();
        $this->assertSame($hash, $player->password);
        $this->assertSame(1, $player->status);
    }
}
