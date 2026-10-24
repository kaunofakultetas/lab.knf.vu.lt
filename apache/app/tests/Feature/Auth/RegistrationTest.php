<?php

// -----------------------------------------------------------
//  [*] RegistrationTest — local accounts and their email link
//
//  The registration form, which is open or closed by settings
//  row 2, the POST that validates it behind reCAPTCHA v3 and
//  creates an inactive account, the verification mail it
//  sends, and the link in that mail, which activates the
//  account once. Google is never asked: every reCAPTCHA
//  verdict is faked, and a request that would ask Google for
//  real fails, as the tests container has no network.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature\Auth;

use App\Http\Controllers\Users\RegisterController;
use App\Mail\UserVerifyMail;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;








// -----------------------------------------------------------
// RegistrationTest
// -----------------------------------------------------------
//
// Mail is faked in every test, so the verification mails
// can be inspected and none is ever delivered.
//
//   submitRegistration — posts the form with valid values,
//                        any of them overridden
//   verificationLink   — a link as the registration mails it
//
// Used by:
//   - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------

final class RegistrationTest extends TestCase
{






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
    // submitRegistration
    // -----------------------------------------------------------
    //
    // Posts the registration form from the registration page
    // with a valid username, email, matching passwords and the
    // reCAPTCHA token; the fields given replace those values.
    //
    // Used by:
    //   - the tests below
    // -----------------------------------------------------------

    private function submitRegistration(array $fields = []): TestResponse
    {
        return $this->from('/register')->post('/register', array_merge([
            'username'              => 'Jonas',
            'email'                 => 'jonas@players.test',
            'password'              => 'long-enough-1',
            'password_confirmation' => 'long-enough-1',
            'g-recaptcha-response'  => self::RECAPTCHA_TOKEN,
        ], $fields));
    }






    // -----------------------------------------------------------
    // verificationLink
    // -----------------------------------------------------------
    //
    // A token as a verification mail carries it — 50 random
    // characters, a dash and the Unix time — after storing its
    // hash on the account given, as send_verification does.
    //
    // Used by:
    //   - the link tests below
    // -----------------------------------------------------------

    private function verificationLink(User $player): string
    {
        $token = Str::random(50) . '-' . now()->getTimestamp();
        $player->update(['verify_token' => hash('sha256', $token)]);
        return $token;
    }






    // -----------------------------------------------------------
    // test_the_form_is_shown_while_registration_is_open
    // -----------------------------------------------------------
    //
    // The page carries the form posting to the registration
    // route, the hidden reCAPTCHA field and the reCAPTCHA
    // script for the configured site key.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_form_is_shown_while_registration_is_open(): void
    {
        $response = $this->get('/register');

        $response->assertOk();
        $response->assertViewIs('users.register');
        $response->assertSee('action="https://lab.knf.vu.lt/register"', false);
        $response->assertSee('name="g-recaptcha-response"', false);
        $response->assertSee('render=test-sitekey', false);
    }






    // -----------------------------------------------------------
    // test_the_page_says_so_while_registration_is_closed
    // -----------------------------------------------------------
    //
    // With registration closed in the settings the page
    // replaces the form with a notice.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_page_says_so_while_registration_is_closed(): void
    {
        $this->setRegistrationOpen(false);

        $response = $this->get('/register');

        $response->assertOk();
        $response->assertSee('Registration is closed!');
        $response->assertDontSee('name="password_confirmation"', false);
    }






    // -----------------------------------------------------------
    // test_registering_creates_an_inactive_account
    // -----------------------------------------------------------
    //
    // A valid form creates an individual (type 1), inactive
    // account with a hashed password, no points, no newsletter
    // and the SHA-256 hash of its verification token, and
    // returns to the form with the success message.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_registering_creates_an_inactive_account(): void
    {
        $this->fakeRecaptcha('register', 0.9);

        $response = $this->submitRegistration();

        $response->assertRedirect('/register');
        $response->assertSessionHas('success', 'You are registered! Check your email for verification link.');
        $player = User::where('email', 'jonas@players.test')->firstOrFail();
        $this->assertSame('Jonas', $player->username);
        $this->assertSame(1, $player->user_type);
        $this->assertSame(0, $player->status);
        $this->assertSame(0, $player->points);
        $this->assertSame(0, $player->newsletter);
        $this->assertNull($player->vu_id);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $player->verify_token);
        $this->assertTrue(Hash::check('long-enough-1', $player->password));
    }






    // -----------------------------------------------------------
    // test_registering_mails_the_verification_token
    // -----------------------------------------------------------
    //
    // The new address receives the verification mail, whose
    // token — 50 random characters, a dash and the time — is
    // stored on the account only as its SHA-256 hash.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_registering_mails_the_verification_token(): void
    {
        $this->fakeRecaptcha('register', 0.9);

        $this->submitRegistration();

        $player = User::where('email', 'jonas@players.test')->firstOrFail();
        Mail::assertSent(UserVerifyMail::class, function (UserVerifyMail $mail) use ($player) {
            return $mail->hasTo('jonas@players.test')
                && preg_match('/^[A-Za-z0-9]{50}-\d+$/', $mail->data['uid']) === 1
                && hash('sha256', $mail->data['uid']) === $player->verify_token;
        });
        Mail::assertSent(UserVerifyMail::class, 1);
    }






    // -----------------------------------------------------------
    // test_usernames_may_use_any_alphabet_digits_and_some_punctuation
    // -----------------------------------------------------------
    //
    // Letters of any alphabet — Lithuanian ones included —
    // digits, spaces, dots, commas, underscores and hyphens are
    // all accepted in a username.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_usernames_may_use_any_alphabet_digits_and_some_punctuation(): void
    {
        $this->fakeRecaptcha('register', 0.9);

        $this->submitRegistration(['username' => 'Žemaitė Jonė-2_v.1,'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cyber_users', ['username' => 'Žemaitė Jonė-2_v.1,']);
    }






    // -----------------------------------------------------------
    // test_passwords_are_kept_exactly_as_typed
    // -----------------------------------------------------------
    //
    // Unlike every other field, passwords are not trimmed: a
    // password with spaces around it is stored, and later
    // checked, with those spaces.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_passwords_are_kept_exactly_as_typed(): void
    {
        $this->fakeRecaptcha('register', 0.9);

        $this->submitRegistration(['password' => '  spaced out  ', 'password_confirmation' => '  spaced out  ']);

        $player = User::where('email', 'jonas@players.test')->firstOrFail();
        $this->assertTrue(Hash::check('  spaced out  ', $player->password));
        $this->assertFalse(Hash::check('spaced out', $player->password));
    }






    // -----------------------------------------------------------
    // test_the_form_is_validated
    // -----------------------------------------------------------
    //
    // Each malformed field is refused with an error filed
    // under it; no account is created and no mail is sent.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    #[DataProvider('malformedForms')]
    public function test_the_form_is_validated(array $fields, string $field): void
    {
        $this->fakeRecaptcha('register', 0.9);

        $response = $this->submitRegistration($fields);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors($field);
        $this->assertDatabaseMissing('cyber_users', ['email' => 'jonas@players.test']);
        Mail::assertNothingSent();
    }






    // -----------------------------------------------------------
    // malformedForms
    // -----------------------------------------------------------
    //
    // The fields that replace the valid ones, and the field
    // the error lands on.
    //
    // Used by:
    //   - test_the_form_is_validated (above)
    // -----------------------------------------------------------

    public static function malformedForms(): array
    {
        return [
            'no username'          => [['username' => ''], 'username'],
            'short username'       => [['username' => 'Jon'], 'username'],
            'overlong username'    => [['username' => str_repeat('j', 256)], 'username'],
            'markup in username'   => [['username' => '<b>Jonas</b>'], 'username'],
            'at sign in username'  => [['username' => 'jonas@home'], 'username'],
            'no email'             => [['email' => ''], 'email'],
            'not an address'       => [['email' => 'jonas-at-players.test'], 'email'],
            'short password'       => [['password' => 'seven77', 'password_confirmation' => 'seven77'], 'password'],
            'unconfirmed password' => [['password_confirmation' => 'something-else'], 'password'],
            'no reCAPTCHA token'   => [['g-recaptcha-response' => ''], 'g-recaptcha-response'],
        ];
    }






    // -----------------------------------------------------------
    // test_a_taken_username_is_refused_whatever_its_case
    // -----------------------------------------------------------
    //
    // Usernames are unique as the database collation compares
    // them, which ignores letter case.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_taken_username_is_refused_whatever_its_case(): void
    {
        $this->makePlayer(['username' => 'jonas']);
        $this->fakeRecaptcha('register', 0.9);

        $this->submitRegistration(['username' => 'JONAS'])->assertSessionHasErrors('username');

        $this->assertDatabaseMissing('cyber_users', ['email' => 'jonas@players.test']);
    }






    // -----------------------------------------------------------
    // test_a_taken_email_is_refused
    // -----------------------------------------------------------
    //
    // The form refuses an address an account already has.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_taken_email_is_refused(): void
    {
        $this->makePlayer(['email' => 'jonas@players.test']);
        $this->fakeRecaptcha('register', 0.9);

        $this->submitRegistration()->assertSessionHasErrors('email');

        $this->assertSame(1, User::where('email', 'jonas@players.test')->count());
    }






    // -----------------------------------------------------------
    // test_a_low_recaptcha_score_is_refused
    // -----------------------------------------------------------
    //
    // A verdict under 0.6 counts as a bot: the form is
    // refused with an error on the reCAPTCHA field.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_low_recaptcha_score_is_refused(): void
    {
        $this->fakeRecaptcha('register', 0.5);

        $this->submitRegistration()->assertSessionHasErrors('g-recaptcha-response');

        $this->assertDatabaseMissing('cyber_users', ['email' => 'jonas@players.test']);
        Mail::assertNothingSent();
    }






    // -----------------------------------------------------------
    // test_a_recaptcha_score_of_exactly_the_threshold_passes
    // -----------------------------------------------------------
    //
    // The threshold itself, 0.6, is a pass.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_recaptcha_score_of_exactly_the_threshold_passes(): void
    {
        $this->fakeRecaptcha('register', 0.6);

        $this->submitRegistration()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cyber_users', ['email' => 'jonas@players.test']);
    }






    // -----------------------------------------------------------
    // test_posting_is_refused_while_registration_is_closed
    // -----------------------------------------------------------
    //
    // A closed registration answers a posted form with 404
    // before validating it — reCAPTCHA is not even asked.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_posting_is_refused_while_registration_is_closed(): void
    {
        $this->setRegistrationOpen(false);

        $this->submitRegistration()->assertNotFound();

        $this->assertDatabaseMissing('cyber_users', ['email' => 'jonas@players.test']);
        Mail::assertNothingSent();
    }






    // -----------------------------------------------------------
    // test_following_the_link_activates_the_account
    // -----------------------------------------------------------
    //
    // The verification link sets the account active, empties
    // its token and sends the player to the login form with a
    // message.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_following_the_link_activates_the_account(): void
    {
        $player = $this->makePlayer(['status' => 0]);
        $token = $this->verificationLink($player);

        $response = $this->get("/verify/{$token}");

        $response->assertRedirect('/');
        $response->assertSessionHas('success', 'User verified! Now you can login.');
        $player->refresh();
        $this->assertSame(1, $player->status);
        $this->assertSame('', $player->verify_token);
    }






    // -----------------------------------------------------------
    // test_a_link_works_only_once
    // -----------------------------------------------------------
    //
    // Once used, the emptied token matches no account, and the
    // same link answers 404.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_link_works_only_once(): void
    {
        $token = $this->verificationLink($this->makePlayer(['status' => 0]));

        $this->get("/verify/{$token}")->assertRedirect('/');

        $this->get("/verify/{$token}")->assertNotFound();
    }






    // -----------------------------------------------------------
    // test_unknown_and_missing_tokens_are_404
    // -----------------------------------------------------------
    //
    // A token that matches no account, and the bare link
    // without one, both answer 404.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_unknown_and_missing_tokens_are_404(): void
    {
        $this->makePlayer(['status' => 0, 'verify_token' => Str::random(50)]);

        $this->get('/verify/' . Str::random(50) . '-' . now()->getTimestamp())->assertNotFound();
        $this->get('/verify/')->assertNotFound();
    }






    // -----------------------------------------------------------
    // test_an_sso_account_is_never_verified_by_link
    // -----------------------------------------------------------
    //
    // The link only ever activates local accounts: one with a
    // vu_id answers 404 even when the token matches.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_sso_account_is_never_verified_by_link(): void
    {
        $player = $this->makeSsoPlayer(['status' => 0]);
        $token = $this->verificationLink($player);

        $this->get("/verify/{$token}")->assertNotFound();

        $this->assertSame(0, $player->refresh()->status);
    }






    // -----------------------------------------------------------
    // test_a_signed_in_player_following_a_link_goes_to_the_dashboard
    // -----------------------------------------------------------
    //
    // The link is a guest page: a signed-in player is sent to
    // the dashboard and the token is left unused.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_signed_in_player_following_a_link_goes_to_the_dashboard(): void
    {
        $pending = $this->makePlayer(['status' => 0]);
        $token = $this->verificationLink($pending);
        $this->signIn($this->makePlayer());

        $this->get("/verify/{$token}")->assertRedirect('/user/dashboard');

        $this->assertSame(hash('sha256', $token), $pending->refresh()->verify_token);
    }






    // -----------------------------------------------------------
    // test_a_new_player_signs_in_after_following_the_link
    // -----------------------------------------------------------
    //
    // The whole way in: register, be refused at the login form
    // while unverified — which mails a fresh link — follow the
    // newest mailed link, sign in.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_new_player_signs_in_after_following_the_link(): void
    {
        $this->fakeRecaptcha('register', 0.9);
        $this->submitRegistration();
        $credentials = ['email' => 'jonas@players.test', 'password' => 'long-enough-1'];

        $this->post('/login', $credentials)->assertRedirect('/');
        $this->assertErrorFlashed('Your account is not verified yet. We sent you a new verification link.');

        $token = Mail::sent(UserVerifyMail::class)->last()->data['uid'];
        $this->get("/verify/{$token}")->assertRedirect('/');
        $this->post('/login', $credentials)->assertRedirect('/user/dashboard');
        $this->assertAuthenticated('users');
    }






    // -----------------------------------------------------------
    // test_a_refused_form_keeps_no_password
    // -----------------------------------------------------------
    //
    // A refused registration comes back with the username and
    // e-mail filled in again, but neither password is kept in
    // the session.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_refused_form_keeps_no_password(): void
    {
        $this->post('/register', ['username' => 'Jo', 'email' => 'jonas@players.test', 'password' => 'typed-secret-1', 'password_confirmation' => 'typed-secret-1']);

        $this->assertSame('Jo', session()->getOldInput('username'));
        $this->assertSame('jonas@players.test', session()->getOldInput('email'));
        $this->assertNull(session()->getOldInput('password'));
        $this->assertNull(session()->getOldInput('password_confirmation'));
    }






    // -----------------------------------------------------------
    // test_a_verification_link_expires_after_a_day
    // -----------------------------------------------------------
    //
    // A link opened 23 hours after it was mailed activates the
    // account; one opened after the day answers 404 and leaves
    // the account inactive.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_verification_link_expires_after_a_day(): void
    {
        $early = $this->makePlayer(['status' => 0]);
        $late = $this->makePlayer(['status' => 0]);
        $earlyLink = $this->verificationLink($early);
        $lateLink = $this->verificationLink($late);

        $this->travel(23)->hours();
        $this->get("/verify/{$earlyLink}")->assertRedirect('/');

        $this->travel(2)->hours();
        $this->get("/verify/{$lateLink}")->assertNotFound();

        $this->assertSame(1, $early->refresh()->status);
        $this->assertSame(0, $late->refresh()->status);
    }






    // -----------------------------------------------------------
    // test_a_verification_link_with_a_changed_time_is_404
    // -----------------------------------------------------------
    //
    // The stored hash covers the time the token ends with, so
    // an expired link whose time is moved on matches no
    // account.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_verification_link_with_a_changed_time_is_404(): void
    {
        $player = $this->makePlayer(['status' => 0]);
        $link = $this->verificationLink($player);
        $this->travel(2)->days();

        $this->get('/verify/' . Str::beforeLast($link, '-') . '-' . now()->getTimestamp())->assertNotFound();

        $this->assertSame(0, $player->refresh()->status);
    }






    // -----------------------------------------------------------
    // test_a_link_stored_as_mailed_is_404
    // -----------------------------------------------------------
    //
    // A token as links were mailed before they expired —
    // stored as is, without a time — no longer activates; the
    // account gets a fresh link at its next sign-in.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_link_stored_as_mailed_is_404(): void
    {
        $token = Str::random(50);
        $player = $this->makePlayer(['status' => 0, 'verify_token' => $token]);

        $this->get("/verify/{$token}")->assertNotFound();

        $this->assertSame(0, $player->refresh()->status);
    }






    // -----------------------------------------------------------
    // test_signing_in_unverified_mails_a_fresh_link
    // -----------------------------------------------------------
    //
    // The right password for an account never verified is
    // refused with a message saying a new link is on its way,
    // and the new link replaces the old one.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_signing_in_unverified_mails_a_fresh_link(): void
    {
        $player = $this->makePlayer(['status' => 0]);
        $old = $this->verificationLink($player);

        $this->post('/login', ['email' => $player->email, 'password' => self::PASSWORD])->assertRedirect('/');

        $this->assertErrorFlashed('Your account is not verified yet. We sent you a new verification link.');
        $this->assertGuest('users');
        $fresh = Mail::sent(UserVerifyMail::class, fn (UserVerifyMail $mail) => $mail->hasTo($player->email))->last()->data['uid'];
        $this->get("/verify/{$old}")->assertNotFound();
        $this->get("/verify/{$fresh}")->assertRedirect('/');
        $this->assertSame(1, $player->refresh()->status);
    }






    // -----------------------------------------------------------
    // test_a_switched_off_account_gets_no_new_link
    // -----------------------------------------------------------
    //
    // An account an admin switched off — verified once, so
    // holding no token — is refused as inactive, and no mail
    // goes out.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_switched_off_account_gets_no_new_link(): void
    {
        $player = $this->makePlayer(['status' => 0, 'verify_token' => '']);

        $this->post('/login', ['email' => $player->email, 'password' => self::PASSWORD])->assertRedirect('/');

        $this->assertErrorFlashed('Your account is not active');
        Mail::assertNothingSent();
    }






    // -----------------------------------------------------------
    // test_a_verification_link_ends_on_the_login_page
    // -----------------------------------------------------------
    //
    // An opened verification link leads to the login page with
    // its message, whatever page an earlier visit left asked for
    // in the session.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_verification_link_ends_on_the_login_page(): void
    {
        $player = $this->makePlayer(['status' => 0]);
        $token = $this->verificationLink($player);

        $this->withSession(['url.intended' => 'https://lab.knf.vu.lt/user/leaderboard'])
            ->get("/verify/{$token}")
            ->assertRedirect('/')
            ->assertSessionHas('success', 'User verified! Now you can login.');
    }






    // -----------------------------------------------------------
    // test_a_refused_recaptcha_says_why
    // -----------------------------------------------------------
    //
    // A reCAPTCHA refusal says in words that the reCAPTCHA
    // check failed, where it once showed the rule's raw key.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_refused_recaptcha_says_why(): void
    {
        $this->fakeRecaptcha('register', 0.1);

        $this->submitRegistration();

        $this->assertSame('The reCAPTCHA check failed, please try again.', session('errors')?->first('g-recaptcha-response'));
    }






    // -----------------------------------------------------------
    // test_the_form_fetches_its_recaptcha_token_as_it_is_sent
    // -----------------------------------------------------------
    //
    // The form names its reCAPTCHA action and site key for the
    // player script, which fetches the token as the form is
    // sent, and the page fetches none as it loads: a token is
    // good for two minutes only, so one fetched with the page
    // would refuse a form filled in more slowly.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_form_fetches_its_recaptcha_token_as_it_is_sent(): void
    {
        $response = $this->get('/register');

        $response->assertSee('data-recaptcha-action="register"', false);
        $response->assertSee('data-recaptcha-key="test-sitekey"', false);
        $response->assertSee('name="g-recaptcha-response"', false);
        $response->assertDontSee('grecaptcha.execute', false);
    }






    // -----------------------------------------------------------
    // test_a_double_clicked_registration_ends_without_a_server_error
    // -----------------------------------------------------------
    //
    // Two registrations sent at once both find the username
    // free, and the later insert meets the username's unique
    // key: it goes back to the form as the taken username it
    // is, not as a server error, and one account stands. Here
    // the second registration runs in full right after the
    // first one's last check.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_double_clicked_registration_ends_without_a_server_error(): void
    {
        $this->fakeRecaptcha('register', 0.9);
        $form = [
            'username'              => 'Jonas',
            'email'                 => 'jonas@players.test',
            'password'              => 'long-enough-1',
            'password_confirmation' => 'long-enough-1',
            'g-recaptcha-response'  => self::RECAPTCHA_TOKEN,
        ];
        $interleaved = false;
        DB::listen(function (QueryExecuted $query) use ($form, &$interleaved) {
            if (!$interleaved && str_starts_with($query->sql, 'select count(*) as aggregate from `cyber_users` where `email` = ?')) {
                $interleaved = true;
                app(RegisterController::class)->store(Request::create('/register', 'POST', $form));
            }
        });

        $response = $this->from('/register')->post('/register', $form);

        $this->assertTrue($interleaved);
        $response->assertRedirect('/register');
        $this->assertSame('The username has already been taken.', session('errors')?->first('username'));
        $this->assertSame(1, User::where('username', 'Jonas')->count());
    }
}
