<?php

// -----------------------------------------------------------
//  [*] PasswordResetTest — the forgotten-password reminder
//
//  Local accounts recover in two steps. The reminder form
//  (behind reCAPTCHA v3) mails a link carrying a fresh
//  50-character token and the time it was mailed; the link,
//  good for an hour, opens a page whose button sets a new
//  random 12-character password, mails it, and uses the
//  link up. The form answers the same whether or not the
//  address has an account, and SSO accounts, whose password
//  lives at VU, never get a reminder.
//
//  The tests take every link from the mail it was sent in,
//  never from the database, so they keep holding when the
//  way tokens are stored changes.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature\Auth;

use App\Mail\SendNewPass;
use App\Mail\SendRemindLink;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;








// -----------------------------------------------------------
// PasswordResetTest
// -----------------------------------------------------------
//
// Mail is faked in every test.
//
//   askForReminder — posts the reminder form for an address
//   mailedLink — the token of a freshly mailed reminder
//
// Used by:
//   - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------

final class PasswordResetTest extends TestCase
{

    // The message the reminder form always answers with
    private const ANSWER = 'If email exists in our database, you will receive reset link.';






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
    // askForReminder
    // -----------------------------------------------------------
    //
    // Posts the reminder form from the reminder page with the
    // reCAPTCHA token, or with the token given.
    //
    // Used by:
    //   - the tests below
    // -----------------------------------------------------------

    private function askForReminder(string $email, string $token = self::RECAPTCHA_TOKEN): TestResponse
    {
        return $this->from('/forgot-password')->post('/forgot-password', [
            'email'                => $email,
            'g-recaptcha-response' => $token,
        ]);
    }






    // -----------------------------------------------------------
    // mailedLink
    // -----------------------------------------------------------
    //
    // Asks for a reminder for the player, with a passing
    // reCAPTCHA verdict, and returns the token of the link
    // the newest reminder mail to the player carries.
    //
    // Used by:
    //   - the link tests below
    // -----------------------------------------------------------

    private function mailedLink(User $player): string
    {
        $this->fakeRecaptcha('forgotpass', 0.9);
        $this->askForReminder($player->email);
        return Mail::sent(SendRemindLink::class, fn (SendRemindLink $mail) => $mail->hasTo($player->email))->last()->data['uid'];
    }






    // -----------------------------------------------------------
    // test_the_reminder_form_is_shown
    // -----------------------------------------------------------
    //
    // The page carries the form posting to the reminder route
    // and the reCAPTCHA field for the forgotpass action.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_reminder_form_is_shown(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertOk();
        $response->assertViewIs('users.forgot-password');
        $response->assertSee('action="https://lab.knf.vu.lt/forgot-password"', false);
        $response->assertSee("{action: 'forgotpass'}", false);
    }






    // -----------------------------------------------------------
    // test_a_local_account_gets_a_reminder_link
    // -----------------------------------------------------------
    //
    // The address of a local account receives the reminder
    // mail with a 50-character token, a dash and the Unix time
    // it was mailed, and the form answers with its usual
    // message.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_local_account_gets_a_reminder_link(): void
    {
        $player = $this->makePlayer();
        $this->fakeRecaptcha('forgotpass', 0.9);
        $this->freezeTime();

        $response = $this->askForReminder($player->email);

        $response->assertRedirect('/forgot-password');
        $response->assertSessionHas('success', self::ANSWER);
        Mail::assertSent(SendRemindLink::class, function (SendRemindLink $mail) use ($player) {
            return $mail->hasTo($player->email)
                && preg_match('/^[A-Za-z0-9]{50}-' . now()->getTimestamp() . '$/', $mail->data['uid']) === 1;
        });
    }






    // -----------------------------------------------------------
    // test_an_account_with_an_empty_vu_id_counts_as_local
    // -----------------------------------------------------------
    //
    // A vu_id that is empty rather than missing still marks a
    // local account, which gets its reminder.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_account_with_an_empty_vu_id_counts_as_local(): void
    {
        $player = $this->makePlayer(['vu_id' => '']);
        $this->fakeRecaptcha('forgotpass', 0.9);

        $this->askForReminder($player->email);

        Mail::assertSent(SendRemindLink::class, 1);
    }






    // -----------------------------------------------------------
    // test_unknown_and_sso_addresses_get_the_same_answer_and_no_mail
    // -----------------------------------------------------------
    //
    // An address with no account, and the address of an SSO
    // account, get the very answer a local account gets —
    // the form tells nothing — but no mail and no token.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_unknown_and_sso_addresses_get_the_same_answer_and_no_mail(): void
    {
        $sso = $this->makeSsoPlayer();
        $this->fakeRecaptcha('forgotpass', 0.9);

        $this->askForReminder('nobody@players.test')->assertSessionHas('success', self::ANSWER);
        $this->askForReminder($sso->email)->assertSessionHas('success', self::ANSWER);

        Mail::assertNothingSent();
        $this->assertNull($sso->refresh()->remember_token);
    }






    // -----------------------------------------------------------
    // test_the_reminder_form_is_validated
    // -----------------------------------------------------------
    //
    // A missing or malformed address and a missing reCAPTCHA
    // token are refused with an error under the field.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    #[DataProvider('malformedReminders')]
    public function test_the_reminder_form_is_validated(string $email, string $token, string $field): void
    {
        $this->fakeRecaptcha('forgotpass', 0.9);

        $response = $this->askForReminder($email, $token);

        $response->assertRedirect('/forgot-password');
        $response->assertSessionHasErrors($field);
        $response->assertSessionMissing('success');
        Mail::assertNothingSent();
    }






    // -----------------------------------------------------------
    // malformedReminders
    // -----------------------------------------------------------
    //
    // Address, token and the field the error lands on.
    //
    // Used by:
    //   - test_the_reminder_form_is_validated (above)
    // -----------------------------------------------------------

    public static function malformedReminders(): array
    {
        return [
            'no address'     => ['', self::RECAPTCHA_TOKEN, 'email'],
            'not an address' => ['jonas-at-players.test', self::RECAPTCHA_TOKEN, 'email'],
            'no token'       => ['jonas@players.test', '', 'g-recaptcha-response'],
        ];
    }






    // -----------------------------------------------------------
    // test_a_low_recaptcha_score_gets_no_reminder
    // -----------------------------------------------------------
    //
    // A verdict under 0.6 refuses the form, account or not.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_low_recaptcha_score_gets_no_reminder(): void
    {
        $player = $this->makePlayer();
        $this->fakeRecaptcha('forgotpass', 0.3);

        $this->askForReminder($player->email)->assertSessionHasErrors('g-recaptcha-response');

        Mail::assertNothingSent();
        $this->assertNull($player->refresh()->remember_token);
    }






    // -----------------------------------------------------------
    // test_opening_the_link_changes_no_password
    // -----------------------------------------------------------
    //
    // The mailed link opens a page with one button, a form
    // posting to the link with the CSRF token; opening it
    // changes no password and mails nothing, so a mail
    // filter or link scanner that opens it uses nothing up.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_opening_the_link_changes_no_password(): void
    {
        $player = $this->makePlayer();
        $link = $this->mailedLink($player);
        $hash = $player->refresh()->password;

        $response = $this->get("/reset/{$link}");

        $response->assertOk();
        $response->assertViewIs('users.reset-password');
        $response->assertSee('action="https://lab.knf.vu.lt/reset/' . $link . '"', false);
        $response->assertSee('name="_token"', false);
        $this->assertSame($hash, $player->refresh()->password);
        Mail::assertNotSent(SendNewPass::class);
    }






    // -----------------------------------------------------------
    // test_the_button_sets_and_mails_a_new_password
    // -----------------------------------------------------------
    //
    // The button of the mailed link's page replaces the
    // password with a random 12-character one, mails it to
    // the account's address and sends the player to the login
    // form.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_button_sets_and_mails_a_new_password(): void
    {
        $player = $this->makePlayer();
        $link = $this->mailedLink($player);

        $response = $this->post("/reset/{$link}");

        $response->assertRedirect('/');
        $response->assertSessionHas('success', 'We just emailed you new password');
        $player->refresh();
        $this->assertFalse(Hash::check(self::PASSWORD, $player->password));
        Mail::assertSent(SendNewPass::class, function (SendNewPass $mail) use ($player) {
            return $mail->hasTo($player->email)
                && $mail->data['email'] === $player->email
                && strlen($mail->data['new_pass']) === 12
                && Hash::check($mail->data['new_pass'], $player->password);
        });
    }






    // -----------------------------------------------------------
    // test_the_mailed_password_signs_the_player_in
    // -----------------------------------------------------------
    //
    // The whole way back in: ask for the reminder, press the
    // button of the mailed link's page, sign in with the
    // mailed password — and no longer with the old one.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_mailed_password_signs_the_player_in(): void
    {
        $player = $this->makePlayer();
        $link = $this->mailedLink($player);

        $this->post("/reset/{$link}")->assertRedirect('/');
        $password = Mail::sent(SendNewPass::class)->first()->data['new_pass'];

        $this->post('/login', ['email' => $player->email, 'password' => self::PASSWORD])->assertRedirect('/');
        $this->assertGuest('users');
        $this->post('/login', ['email' => $player->email, 'password' => $password])->assertRedirect('/user/dashboard');
        $this->assertAuthenticatedAs($player, 'users');
    }






    // -----------------------------------------------------------
    // test_a_link_works_only_once
    // -----------------------------------------------------------
    //
    // A used link answers 404 afterwards — its page and its
    // button alike — and mails nothing more.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_link_works_only_once(): void
    {
        $link = $this->mailedLink($this->makePlayer());
        $this->post("/reset/{$link}")->assertRedirect('/');

        $this->get("/reset/{$link}")->assertNotFound();
        $this->post("/reset/{$link}")->assertNotFound();

        Mail::assertSent(SendNewPass::class, 1);
    }






    // -----------------------------------------------------------
    // test_an_unknown_link_is_404
    // -----------------------------------------------------------
    //
    // A token no reminder mailed answers 404, its page and its
    // button alike, even while a real reminder is pending,
    // and no password changes.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_unknown_link_is_404(): void
    {
        $player = $this->makePlayer();
        $this->mailedLink($player);
        $hash = $player->refresh()->password;
        $unknown = Str::random(50) . '-' . now()->getTimestamp();

        $this->get("/reset/{$unknown}")->assertNotFound();
        $this->post("/reset/{$unknown}")->assertNotFound();

        $this->assertSame($hash, $player->refresh()->password);
        Mail::assertNotSent(SendNewPass::class);
    }






    // -----------------------------------------------------------
    // test_a_link_expires_after_an_hour
    // -----------------------------------------------------------
    //
    // A link still opens 59 minutes after it was mailed; past
    // the hour it answers 404 like an unknown one, its button
    // too, and mails no password — however long it waited in
    // a mailbox or a log.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_link_expires_after_an_hour(): void
    {
        $link = $this->mailedLink($this->makePlayer());

        $this->travel(59)->minutes();
        $this->get("/reset/{$link}")->assertOk();

        $this->travel(2)->minutes();
        $this->get("/reset/{$link}")->assertNotFound();
        $this->post("/reset/{$link}")->assertNotFound();
        Mail::assertNotSent(SendNewPass::class);
    }






    // -----------------------------------------------------------
    // test_a_link_with_a_changed_time_is_404
    // -----------------------------------------------------------
    //
    // The stored hash covers the time the token ends with, so
    // an expired link whose time is moved on matches no
    // account.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_link_with_a_changed_time_is_404(): void
    {
        $link = $this->mailedLink($this->makePlayer());
        $this->travel(2)->hours();

        $moved = Str::beforeLast($link, '-') . '-' . now()->getTimestamp();

        $this->get("/reset/{$moved}")->assertNotFound();
        $this->post("/reset/{$moved}")->assertNotFound();
        Mail::assertNotSent(SendNewPass::class);
    }






    // -----------------------------------------------------------
    // test_a_link_without_a_time_is_404
    // -----------------------------------------------------------
    //
    // A token as reminders were mailed before links expired —
    // 50 characters, no time — answers 404 even with its hash
    // stored, so such links stop working.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_link_without_a_time_is_404(): void
    {
        $token = Str::random(50);
        $this->makePlayer(['remember_token' => hash('sha256', $token)]);

        $this->get("/reset/{$token}")->assertNotFound();
        $this->post("/reset/{$token}")->assertNotFound();
        Mail::assertNotSent(SendNewPass::class);
    }






    // -----------------------------------------------------------
    // test_the_link_is_a_guest_page
    // -----------------------------------------------------------
    //
    // A signed-in player opening a reminder link, or posting
    // its button, is sent to the dashboard; the link is not
    // used up and still works for a guest afterwards.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_link_is_a_guest_page(): void
    {
        $link = $this->mailedLink($this->makePlayer());
        $this->signIn($this->makePlayer());

        $this->get("/reset/{$link}")->assertRedirect('/user/dashboard');
        $this->post("/reset/{$link}")->assertRedirect('/user/dashboard');
        Mail::assertNotSent(SendNewPass::class);

        $this->app['auth']->guard('users')->logout();
        $this->post("/reset/{$link}")->assertRedirect('/');
    }






    // -----------------------------------------------------------
    // test_a_second_reminder_replaces_the_first_link
    // -----------------------------------------------------------
    //
    // Asking twice mails a new token, and only the newest
    // link still works.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_second_reminder_replaces_the_first_link(): void
    {
        $player = $this->makePlayer();
        $first = $this->mailedLink($player);
        $second = $this->mailedLink($player);

        $this->assertNotSame($first, $second);
        $this->post("/reset/{$first}")->assertNotFound();
        $this->post("/reset/{$second}")->assertRedirect('/');
    }






    // -----------------------------------------------------------
    // test_reminder_tokens_are_not_stored_as_mailed
    // -----------------------------------------------------------
    //
    // The token a reminder mail carries is stored only as its
    // hash, so the users table alone resets no password, and
    // Laravel's password_resets table never holds it either.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_reminder_tokens_are_not_stored_as_mailed(): void
    {
        $player = $this->makePlayer();
        $link = $this->mailedLink($player);

        $this->assertSame(hash('sha256', $link), $player->refresh()->remember_token);
        $this->assertFalse(DB::table('password_resets')->where('token', $link)->exists());
    }






    // -----------------------------------------------------------
    // test_signing_out_keeps_a_reminder_link
    // -----------------------------------------------------------
    //
    // A player who asked for a reminder and then signs out of
    // a session left open still has a working link: Laravel's
    // logout no longer renews remember_token, where the
    // reminder's hash is kept. A real request loads the
    // account afresh, reminder and all, while the test's guard
    // keeps the model it is handed — hence the refresh.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_signing_out_keeps_a_reminder_link(): void
    {
        $player = $this->makePlayer();
        $link = $this->mailedLink($player);

        $this->signIn($player->refresh())->post('/user/logout')->assertRedirect('/');

        $this->get("/reset/{$link}")->assertOk();
        $this->assertSame(hash('sha256', $link), $player->refresh()->remember_token);
    }
}
