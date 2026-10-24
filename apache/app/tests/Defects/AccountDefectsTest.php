<?php

// -----------------------------------------------------------
//  [*] AccountDefectsTest — open defects of accounts and
//  sessions
//
//  Each test states the behaviour the lab wants where
//  registration, sign-in, the account mails and the
//  session fall short today, under a LAB-nn id;
//  KnownDefects reports it incomplete while the defect is
//  open and fails it once a fix lands. The banner of every
//  test gives the severity, the mechanism, naming its files
//  and methods, and the fix.
//
//  Used by:
//    - PHPUnit — the Defects suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Defects;

use App\Mail\UserVerifyMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Tests\Support\KnownDefects;
use Tests\TestCase;








// -----------------------------------------------------------
// AccountDefectsTest
// -----------------------------------------------------------
//
// LAB-73, LAB-74, LAB-78, LAB-79, LAB-80, LAB-85 to LAB-90
// and LAB-92.
//
// Used by:
//   - PHPUnit — the Defects suite (runTests.sh)
// -----------------------------------------------------------

final class AccountDefectsTest extends TestCase
{

    // assertDefectStillOpen, which reports a test incomplete
    // while its defect is open
    use KnownDefects;






    // -----------------------------------------------------------
    // test_lab_73_signing_in_to_an_unverified_account_mails_no_flood_of_links
    // -----------------------------------------------------------
    //
    // Known bug LAB-73 (medium): signing in to an account never
    // verified mails a fresh verification link every time
    // (UserAuthController::login, through
    // RegisterController::send_verification), with nothing
    // between two mails, so one registration, the only step
    // behind reCAPTCHA, lets anyone send any number of
    // verification mails to any address by repeating the
    // login: an inbox flooded in the lab's name, and once the
    // Mailjet quota is spent, no verification or reminder mail
    // reaches anyone. The lab's choice of no login throttling
    // is not at stake — this is a mail the login sends. Here
    // the login is sent three times. Fix: remember when the
    // last link was mailed, in the cache or a column, and mail
    // a new one only some minutes later.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_73_signing_in_to_an_unverified_account_mails_no_flood_of_links(): void
    {
        Mail::fake();
        $player = $this->makePlayer(['status' => 0]);
        $player->forceFill(['verify_token' => hash('sha256', Str::random(50) . '-' . time()), 'last_login' => null])->save();

        foreach (range(1, 3) as $attempt) {
            $this->from('/')->post('/login', ['email' => $player->email, 'password' => self::PASSWORD]);
        }

        $this->assertDefectStillOpen('LAB-73', function () {
            $this->assertLessThanOrEqual(1, Mail::sent(UserVerifyMail::class)->count(), 'every sign-in mails another link');
        });
    }






    // -----------------------------------------------------------
    // test_lab_74_a_refused_guest_form_keeps_only_its_own_fields
    // -----------------------------------------------------------
    //
    // Known bug LAB-74 (medium): a refused guest form puts
    // everything the visitor sent back into the session for
    // the next page — UserAuthController::login and
    // RegisterController::store flash all the input but the
    // passwords, and RegisterController::forgotpassword's
    // validation flashes it all — and the session lives in
    // Redis for the lab's 36 hours. A visitor without a session
    // can add up to PHP's 8 MB of made-up fields to a refused
    // form, and each such request leaves a fresh session of
    // that size in a Redis without a memory limit
    // (docker-compose.yml): a thousand of them hold 8 GB of the
    // shared host's memory, and they need no account. Here a
    // megabyte rides along with each of the three forms. Fix:
    // flash only the fields the form shows again, the e-mail
    // and the username, and give Redis a memory limit.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_74_a_refused_guest_form_keeps_only_its_own_fields(): void
    {
        $padding = ['padding' => str_repeat('x', 1000000)];
        $forms = [
            '/login' => ['/', ['email' => 'not-an-address', 'password' => 'short']],
            '/register' => ['/register', ['username' => 'Jo', 'email' => 'not-an-address', 'password' => 'short', 'password_confirmation' => 'short']],
            '/forgot-password' => ['/forgot-password', ['email' => 'not-an-address']],
        ];

        $sizes = [];
        foreach ($forms as $action => [$page, $fields]) {
            session()->flush();
            $this->from($page)->post($action, $fields + $padding);
            $sizes[$action] = strlen(serialize(session()->all()));
        }

        $this->assertDefectStillOpen('LAB-74', function () use ($sizes) {
            foreach ($sizes as $action => $size) {
                $this->assertLessThan(65536, $size, "a refused {$action} keeps the megabyte sent in the session");
            }
        });
    }






    // -----------------------------------------------------------
    // test_lab_78_a_form_refused_with_a_list_for_a_field_shows_again
    // -----------------------------------------------------------
    //
    // Known bug LAB-78 (low): since the views' Form:: calls
    // became plain Blade (2026-10-24), a field refilled from a
    // refused form prints old() as it comes, and a field sent
    // as a list — only a hand-made request sends one — comes
    // back as an array: {{ }} hands it to htmlspecialchars, a
    // TypeError, and a select compares it as a string, an
    // "Array to string conversion" error, so the next page is
    // a 500 for the one who sent it. laravelcollective's
    // FormBuilder took the list's first value instead. Here the
    // login, registration and reminder forms and the panel's
    // challenge form are sent with lists. Fix: refill a field
    // only from old input that is text, in one place every
    // view uses.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_78_a_form_refused_with_a_list_for_a_field_shows_again(): void
    {
        $statuses = [];
        $this->from('/')->post('/login', ['email' => ['jonas@players.test'], 'password' => 'long-enough-1']);
        $statuses['the login page'] = $this->get('/')->status();
        $this->from('/register')->post('/register', ['username' => ['Jonas'], 'email' => ['jonas@players.test'], 'password' => 'long-enough-1', 'password_confirmation' => 'long-enough-1']);
        $statuses['the registration page'] = $this->get('/register')->status();
        $this->from('/forgot-password')->post('/forgot-password', ['email' => ['jonas@players.test']]);
        $statuses['the reminder page'] = $this->get('/forgot-password')->status();
        $this->signInAdmin($this->makeAdmin(true))->from('/control-panel/challenges/create')->post('/control-panel/challenges/store', ['name' => ['Task'], 'partner_id' => ['1'], 'points' => 'many']);
        $statuses["the panel's challenge form"] = $this->get('/control-panel/challenges/create')->status();

        $this->assertDefectStillOpen('LAB-78', function () use ($statuses) {
            foreach ($statuses as $page => $status) {
                $this->assertSame(200, $status, "{$page} answers {$status} after a form refused with a list for a field");
            }
        });
    }






    // -----------------------------------------------------------
    // test_lab_79_an_address_the_mailer_refuses_is_refused_at_registration
    // -----------------------------------------------------------
    //
    // Known bug LAB-79 (low): Laravel's email rule, an RFC
    // check, lets through addresses Symfony's mailer refuses to
    // write to — a comment in the local part, or an address
    // literal for a domain — and RegisterController::store
    // makes the account before it mails the verification link,
    // so the mail ends the request on a 500 and leaves an
    // account that can never be verified: the address is
    // taken for a new registration, every login mails a new
    // link and ends on a 500 again (send_verification), and so
    // does the reminder form. Here an address with a comment
    // registers. Fix: check the address the way the mailer
    // does, or with the email rule's strict mode, before the
    // account is made.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_79_an_address_the_mailer_refuses_is_refused_at_registration(): void
    {
        $this->fakeRecaptcha('register', 0.9);

        $response = $this->from('/register')->post('/register', [
            'username'              => 'Commented',
            'email'                 => 'jonas(work)@players.test',
            'password'              => 'long-enough-1',
            'password_confirmation' => 'long-enough-1',
            'g-recaptcha-response'  => self::RECAPTCHA_TOKEN,
        ]);

        $this->assertDefectStillOpen('LAB-79', function () use ($response) {
            $this->assertSame(0, User::where('email', 'jonas(work)@players.test')->count(), 'the account is made, and its mail fails');
            $response->assertSessionHasErrors('email');
        });
    }






    // -----------------------------------------------------------
    // test_lab_80_a_link_a_mail_scanner_opened_first_still_serves_the_player
    // -----------------------------------------------------------
    //
    // Known bug LAB-80 (low): the verification link activates
    // the account on its GET and empties the token
    // (RegisterController::verify), so it works once, and the
    // mail scanners that open a link before its reader does —
    // Microsoft 365's Safe Links checks a link as it is
    // clicked — use it up: the account is verified, but the
    // player's own click ends on "Error 404 Page not found"
    // (ValidateVerify), which reads as a failed verification.
    // The reminder link was made safe from this already: its
    // GET only shows a page with a button. Here the link is
    // opened twice, as a scanner and then the player do.
    // RegistrationTest::test_a_link_works_only_once holds
    // today's 404 and changes with the fix. Fix: verify with a
    // button, as the reminder does, or answer a used link with
    // the login page.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_80_a_link_a_mail_scanner_opened_first_still_serves_the_player(): void
    {
        $player = $this->makePlayer(['status' => 0]);
        $token = Str::random(50) . '-' . now()->getTimestamp();
        $player->forceFill(['verify_token' => hash('sha256', $token)])->save();

        $this->get("/verify/{$token}");
        $response = $this->get("/verify/{$token}");

        $this->assertDefectStillOpen('LAB-80', function () use ($response) {
            $this->assertNotSame(404, $response->status(), "the player's own click ends on Page not found");
        });
    }






    // -----------------------------------------------------------
    // test_lab_85_the_confirm_password_label_belongs_to_its_own_box
    // -----------------------------------------------------------
    //
    // Known bug LAB-85 (low): on the registration page the
    // "Confirm Password" label points at the password box
    // (for="login-password", users/register.blade.php), and the
    // theme lays every label over its box's text line, where
    // it is clicked: a player who clicks the words "Confirm
    // Password" and types adds the confirmation to the end of
    // the password, and the form comes back with "The password
    // field confirmation does not match" and both boxes empty.
    // Fix: point the label at the confirmation box.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_85_the_confirm_password_label_belongs_to_its_own_box(): void
    {
        $html = $this->get('/register')->getContent();

        preg_match('/<input[^>]*name="password_confirmation"[^>]*>/', $html, $box);
        preg_match('/\bid="([^"]+)"/', $box[0] ?? '', $id);
        preg_match('#<label[^>]*\bfor="([^"]+)"[^>]*>\s*Confirm Password\s*</label>#', $html, $label);
        $this->assertNotEmpty($id);
        $this->assertNotEmpty($label);

        $this->assertDefectStillOpen('LAB-85', function () use ($id, $label) {
            $this->assertSame($id[1], $label[1], 'the Confirm Password label points at another box');
        });
    }






    // -----------------------------------------------------------
    // test_lab_86_an_error_message_never_takes_the_place_of_its_label
    // -----------------------------------------------------------
    //
    // Known bug LAB-86 (low): the theme's front.js, copied into
    // both script bundles, finds a box's floating label as the
    // last element of the box's group
    // (parentElement.lastElementChild), but the login and
    // reminder forms print a field's error message after the
    // label (users/login.blade.php,
    // users/forgot-password.blade.php), so with an error
    // showing — a password under 8 characters, an e-mail the
    // rule refuses — focusing the box, or a refilled value as
    // the page loads, turns the red message into a grey
    // floating label and leaves the real label over the typed
    // text. Here the login comes back with a password error
    // and the reminder with an e-mail error; the test takes
    // either fix. Fix: find the label by its class in front.js
    // and the bundles, or print the message before the label.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_86_an_error_message_never_takes_the_place_of_its_label(): void
    {
        $byPosition = false;
        foreach (['resources/views/assets/js/front.js', 'public/assets/js/app.js', 'public/assets/js/app.min.js', 'public/assets/js/app_admin.js', 'public/assets/js/app_admin.min.js'] as $script) {
            $byPosition = $byPosition || str_contains(file_get_contents(base_path($script)), 'parentElement.lastElementChild');
        }
        $this->from('/')->post('/login', ['email' => 'jonas@players.test', 'password' => 'short']);
        $login = $this->get('/')->getContent();
        $this->from('/forgot-password')->post('/forgot-password', ['email' => 'not-an-address']);
        $reminder = $this->get('/forgot-password')->getContent();
        $this->assertStringContainsString('class="text-danger"', $login);
        $this->assertStringContainsString('class="text-danger"', $reminder);

        $this->assertDefectStillOpen('LAB-86', function () use ($byPosition, $login, $reminder) {
            if (! $byPosition) {
                return;
            }
            foreach (['the login page' => $login, 'the reminder page' => $reminder] as $page => $html) {
                preg_match_all('#<div class="input-material-group[^"]*">(.*?)</div>#s', $html, $groups);
                foreach ($groups[1] as $group) {
                    if (str_contains($group, 'class="input-material"')) {
                        $this->assertStringEndsWith('</label>', trim($group), "on {$page} a field's error message sits where front.js looks for its label");
                    }
                }
            }
        });
    }






    // -----------------------------------------------------------
    // test_lab_87_a_wrong_password_keeps_the_email_typed
    // -----------------------------------------------------------
    //
    // Known bug LAB-87 (low): a login refused for its
    // credentials, or for an account switched off or never
    // verified, goes back to the login page without the e-mail
    // typed (UserAuthController::login: only the refusal of
    // the form's rules flashes the input), so a player who
    // mistyped the password types the e-mail again too. Here a
    // wrong password is sent. Fix: flash the e-mail on those
    // refusals as well.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_87_a_wrong_password_keeps_the_email_typed(): void
    {
        $player = $this->makePlayer();

        $this->from('/')->post('/login', ['email' => $player->email, 'password' => 'not-the-password-1']);

        $this->assertErrorFlashed('Wrong login credentials');
        $this->assertDefectStillOpen('LAB-87', function () use ($player) {
            $this->assertSame($player->email, session()->getOldInput('email'), 'the login page comes back with the e-mail box empty');
        });
    }






    // -----------------------------------------------------------
    // test_lab_88_no_other_vu_host_can_set_the_session_cookie
    // -----------------------------------------------------------
    //
    // Known bug LAB-88 (low): the session cookie is named
    // vu_knf_lab_session (config/session.php), a name any other
    // host under vu.lt may set for the whole domain — a
    // deliberately vulnerable challenge host, or any page a
    // student controls there. A script on such a host plants
    // the attacker's own lab session cookie in the victim's
    // browser with a longer path, such as /user/challenge,
    // which the browser then sends first, and PHP keeps the
    // first of two cookies of one name: the victim's challenge
    // pages run in the attacker's session, Laravel sets the
    // lab's own cookie to it, and every flag the victim then
    // solves scores for the attacker. A cookie whose name
    // starts with __Host- can only be set by the lab's own
    // host, Secure, for the path / and without a domain, as
    // the lab's cookie already is. Fix: name the cookie
    // __Host-vu_knf_lab_session; everyone is signed out once.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_88_no_other_vu_host_can_set_the_session_cookie(): void
    {
        $this->assertTrue(config('session.secure'));
        $this->assertSame('/', config('session.path'));
        $this->assertNull(config('session.domain'));

        $this->assertDefectStillOpen('LAB-88', function () {
            $this->assertStringStartsWith('__Host-', config('session.cookie'), 'another host under vu.lt can set the session cookie');
        });
    }






    // -----------------------------------------------------------
    // test_lab_89_a_new_password_ends_the_accounts_other_sessions
    // -----------------------------------------------------------
    //
    // Known bug LAB-89 (low): a new password leaves every
    // other session of the account signed in. The auth.session
    // middleware, Laravel's AuthenticateSession, which signs a
    // session out once its account's password changes, is
    // registered in Http/Kernel.php but on no route, so a
    // session left open on a lab computer, or stolen, outlives
    // the owner's password reset
    // (RegisterController::reset_pass) and a main admin's new
    // password for the account
    // (Admins\UsersController::update) for as long as it is
    // used. Accounts without a password, VU accounts, are left
    // alone by the middleware. Here the password changes
    // between two pages. Fix: add auth.session after
    // auth:users on the player routes, and name the login page
    // with AuthenticateSession::redirectUsing — without it
    // Laravel 13 answers the ended session with an empty 401.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_89_a_new_password_ends_the_accounts_other_sessions(): void
    {
        $player = $this->makePlayer();
        $this->signIn($player)->get('/user/leaderboard')->assertOk();

        $player->update(['password' => Hash::make('a-brand-new-password-1')]);
        $response = $this->get('/user/leaderboard');

        $this->assertDefectStillOpen('LAB-89', function () use ($response) {
            $this->assertTrue($response->isRedirect(), 'the session goes on after the password changed');
            $this->assertGuest('users');
        });
    }






    // -----------------------------------------------------------
    // test_lab_90_the_mail_login_never_goes_out_unencrypted
    // -----------------------------------------------------------
    //
    // Known bug LAB-90 (low): the mail goes out over SMTP on
    // port 587, where Laravel 13 ignores config/mail.php's
    // 'encryption' => 'tls' and Symfony's mailer turns to TLS
    // only if the server offers STARTTLS, so an attacker on
    // the way who strips the offer reads the SMTP login — the
    // Mailjet API key and secret — and every mail in clear.
    // Laravel 10 sent the same way. Fix: 'require_tls' => true
    // on the smtp mailer, or port 465 with TLS from the start.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_90_the_mail_login_never_goes_out_unencrypted(): void
    {
        $transport = Mail::mailer('smtp')->getSymfonyTransport();
        $stream = $transport->getStream();

        $this->assertDefectStillOpen('LAB-90', function () use ($transport, $stream) {
            $this->assertTrue($transport->isTlsRequired() || ($stream instanceof SocketStream && $stream->isTLS()), 'the mailer sends without TLS when STARTTLS is not offered');
        });
    }






    // -----------------------------------------------------------
    // test_lab_92_a_login_ends_on_the_dashboard_whatever_address_was_asked_for
    // -----------------------------------------------------------
    //
    // Known bug LAB-92 (low): Symfony 7.4's Request::create
    // refuses an address with a backslash in its path with a
    // BadRequestException, which Controller::opensByGet does
    // not catch — it expects only the router's own refusals —
    // so a login whose page asked for before holds one ends on
    // a 400 instead of the dashboard, the sign-in done. Only a
    // hand-made request leaves such an address (a browser
    // turns \ into /), and only for the one who sends it. Here
    // the session holds such an address as the login is sent.
    // Fix: count any address Request::create refuses as one no
    // GET opens.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_92_a_login_ends_on_the_dashboard_whatever_address_was_asked_for(): void
    {
        $player = $this->makePlayer();

        $response = $this->withSession(['url.intended' => url('/user/challenge/a\\b')])
            ->from('/')
            ->post('/login', ['email' => $player->email, 'password' => self::PASSWORD]);

        $this->assertDefectStillOpen('LAB-92', function () use ($response) {
            $response->assertRedirect('/user/dashboard');
        });
    }
}
