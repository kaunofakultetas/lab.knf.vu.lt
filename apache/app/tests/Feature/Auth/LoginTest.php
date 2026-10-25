<?php

// -----------------------------------------------------------
//  [*] LoginTest — signing in with a local account
//
//  The login form on the front page, the POST that checks
//  it, the gates in front of the guest and member pages, and
//  signing out. Local accounts are the ones made through the
//  registration form; VU accounts come in through the SSO,
//  which SsoLoginTest covers.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature\Auth;

use App\Mail\UserVerifyMail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;








// -----------------------------------------------------------
// LoginTest
// -----------------------------------------------------------
//
// Every test starts as a guest and signs in only when it
// says so.
//
// Used by:
//   - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------

final class LoginTest extends TestCase
{






    // -----------------------------------------------------------
    // test_the_front_page_shows_the_login_form
    // -----------------------------------------------------------
    //
    // A guest opening the site gets the login form, which
    // posts to the login route and links the VU SSO, the
    // password reminder and the registration form.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_front_page_shows_the_login_form(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertViewIs('users.login');
        $response->assertSee('action="https://lab.knf.vu.lt/login"', false);
        $response->assertSee('href="/cp"', false);
        $response->assertSee('href="https://lab.knf.vu.lt/forgot-password"', false);
        $response->assertSee('href="https://lab.knf.vu.lt/register"', false);
    }






    // -----------------------------------------------------------
    // test_a_verified_player_signs_in_and_lands_on_the_dashboard
    // -----------------------------------------------------------
    //
    // The right email and password of an active account open
    // a session on the users guard and lead to the dashboard.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_verified_player_signs_in_and_lands_on_the_dashboard(): void
    {
        $player = $this->makePlayer();

        $response = $this->post('/login', ['email' => $player->email, 'password' => self::PASSWORD]);

        $response->assertRedirect('/user/dashboard');
        $this->assertAuthenticatedAs($player, 'users');
    }






    // -----------------------------------------------------------
    // test_signing_in_records_the_time_and_the_client_address
    // -----------------------------------------------------------
    //
    // A successful login stamps last_login with the current
    // time and last_ip with the address Apache saw the client
    // at — the admin user list shows the time; no page shows
    // the address. The test runs in UTC (inUtc), as a local
    // time the app stored, read back the night the clocks go
    // back, may be an hour off.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_signing_in_records_the_time_and_the_client_address(): void
    {
        $this->inUtc();
        $_SERVER['REMOTE_ADDR'] = '203.0.113.7';
        $player = $this->makePlayer();

        $this->post('/login', ['email' => $player->email, 'password' => self::PASSWORD]);

        $player->refresh();
        $this->assertSame('203.0.113.7', $player->last_ip);
        $this->assertEqualsWithDelta(time(), strtotime($player->last_login), 60);
    }






    // -----------------------------------------------------------
    // test_a_wrong_password_is_refused
    // -----------------------------------------------------------
    //
    // A wrong password sends the guest back to the form with
    // the generic credentials error and no session.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_wrong_password_is_refused(): void
    {
        $player = $this->makePlayer();

        $response = $this->post('/login', ['email' => $player->email, 'password' => 'not-the-password']);

        $response->assertRedirect('/');
        $this->assertErrorFlashed('Wrong login credentials');
        $this->assertGuest('users');
    }






    // -----------------------------------------------------------
    // test_an_unknown_email_gets_the_same_answer_as_a_wrong_password
    // -----------------------------------------------------------
    //
    // The form never tells which emails have accounts: an
    // unknown address gets the very error a wrong password
    // gets.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_unknown_email_gets_the_same_answer_as_a_wrong_password(): void
    {
        $response = $this->post('/login', ['email' => 'nobody@players.test', 'password' => self::PASSWORD]);

        $response->assertRedirect('/');
        $this->assertErrorFlashed('Wrong login credentials');
        $this->assertGuest('users');
    }






    // -----------------------------------------------------------
    // test_an_unverified_account_cannot_sign_in
    // -----------------------------------------------------------
    //
    // An inactive account (status 0) with no verification
    // link pending is signed out again at once, with a message
    // saying it is not active, even though its password is
    // right. An account still waiting for its link is mailed a
    // fresh one instead, which RegistrationTest covers.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_unverified_account_cannot_sign_in(): void
    {
        $player = $this->makePlayer(['status' => 0]);

        $response = $this->post('/login', ['email' => $player->email, 'password' => self::PASSWORD]);

        $response->assertRedirect('/');
        $this->assertErrorFlashed('Your account is not active');
        $this->assertGuest('users');
    }






    // -----------------------------------------------------------
    // test_the_login_form_is_validated
    // -----------------------------------------------------------
    //
    // Malformed input is refused before any account is looked
    // up: the email must be an address, the password eight to
    // 255 characters. The error is filed under the field.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    #[DataProvider('malformedLogins')]
    public function test_the_login_form_is_validated(string $email, string $password, string $field): void
    {
        $response = $this->post('/login', ['email' => $email, 'password' => $password]);

        $response->assertRedirect('/');
        $response->assertSessionHasErrors($field);
        $this->assertGuest('users');
    }






    // -----------------------------------------------------------
    // malformedLogins
    // -----------------------------------------------------------
    //
    // Email, password and the field the error lands on.
    //
    // Used by:
    //   - test_the_login_form_is_validated (above)
    // -----------------------------------------------------------

    public static function malformedLogins(): array
    {
        return [
            'no email'          => ['', self::PASSWORD, 'email'],
            'not an address'    => ['player-at-players.test', self::PASSWORD, 'email'],
            'no password'       => ['player@players.test', '', 'password'],
            'short password'    => ['player@players.test', 'seven77', 'password'],
            'overlong password' => ['player@players.test', str_repeat('p', 256), 'password'],
        ];
    }






    // -----------------------------------------------------------
    // test_a_signed_in_player_skips_the_guest_pages
    // -----------------------------------------------------------
    //
    // The login, registration and reminder pages send a
    // signed-in player straight to the player dashboard.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    #[DataProvider('guestPages')]
    public function test_a_signed_in_player_skips_the_guest_pages(string $page): void
    {
        $this->signIn($this->makePlayer());

        $this->get($page)->assertRedirect('/user/dashboard');
    }






    // -----------------------------------------------------------
    // test_a_signed_in_admin_skips_the_guest_pages
    // -----------------------------------------------------------
    //
    // The same pages send a signed-in admin to the admin
    // dashboard.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    #[DataProvider('guestPages')]
    public function test_a_signed_in_admin_skips_the_guest_pages(string $page): void
    {
        $this->signInAdmin($this->makeAdmin());

        $this->get($page)->assertRedirect('/control-panel/dashboard');
    }






    // -----------------------------------------------------------
    // guestPages
    // -----------------------------------------------------------
    //
    // The player side's pages behind the guest middleware
    // that need no token to open.
    //
    // Used by:
    //   - test_a_signed_in_player_skips_the_guest_pages,
    //     test_a_signed_in_admin_skips_the_guest_pages (above)
    // -----------------------------------------------------------

    public static function guestPages(): array
    {
        return [
            'login'        => ['/'],
            'registration' => ['/register'],
            'reminder'     => ['/forgot-password'],
        ];
    }






    // -----------------------------------------------------------
    // test_player_pages_send_guests_to_the_login_form
    // -----------------------------------------------------------
    //
    // Every page under /user — except the SSO logout — needs a
    // player session; a guest is redirected to the front page
    // before any of them runs.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    #[DataProvider('playerPages')]
    public function test_player_pages_send_guests_to_the_login_form(string $method, string $page): void
    {
        $this->call($method, $page)->assertRedirect('/');
    }






    // -----------------------------------------------------------
    // playerPages
    // -----------------------------------------------------------
    //
    // One request per /user route behind auth:users, with
    // made-up ids — the guard answers before they are read.
    //
    // Used by:
    //   - test_player_pages_send_guests_to_the_login_form
    //     (above)
    // -----------------------------------------------------------

    public static function playerPages(): array
    {
        return [
            'dashboard'         => ['GET', '/user/dashboard'],
            'challenge'         => ['GET', '/user/challenge/some-challenge'],
            'group challenge'   => ['GET', '/user/challenge/some-challenge/some-group'],
            'answer'            => ['POST', '/user/challenge/some-challenge'],
            'group answer'      => ['POST', '/user/challenge/some-challenge/some-group'],
            'vote'              => ['POST', '/user/challenge/vote/up/some-challenge'],
            'leaderboard'       => ['GET', '/user/leaderboard'],
            'group'             => ['GET', '/user/group/some-group'],
            'group leaderboard' => ['GET', '/user/group/leaderboard/some-group'],
            'feedback dialog'   => ['GET', '/user/dialog/feedback'],
            'feedback'          => ['POST', '/user/feedback'],
            'logout'            => ['POST', '/user/logout'],
        ];
    }






    // -----------------------------------------------------------
    // test_scripts_without_a_session_get_401_instead_of_a_redirect
    // -----------------------------------------------------------
    //
    // A request that asks for JSON is answered 401 rather than
    // redirected to the login form.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_scripts_without_a_session_get_401_instead_of_a_redirect(): void
    {
        $this->getJson('/user/leaderboard')->assertUnauthorized();
    }






    // -----------------------------------------------------------
    // test_signing_in_returns_the_player_to_the_page_asked_for
    // -----------------------------------------------------------
    //
    // A guest bounced from a player page lands on that page,
    // not on the dashboard, once signed in.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_signing_in_returns_the_player_to_the_page_asked_for(): void
    {
        $player = $this->makePlayer();

        $this->get('/user/leaderboard')->assertRedirect('/');
        $response = $this->post('/login', ['email' => $player->email, 'password' => self::PASSWORD]);

        $response->assertRedirect('/user/leaderboard');
    }






    // -----------------------------------------------------------
    // test_signing_out_ends_the_session
    // -----------------------------------------------------------
    //
    // Signing out of a local account closes the session and
    // returns to the front page.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_signing_out_ends_the_session(): void
    {
        $this->signIn($this->makePlayer());

        $this->post('/user/logout')->assertRedirect('/');

        $this->assertGuest('users');
    }






    // -----------------------------------------------------------
    // test_the_logout_link_matches_the_way_the_player_signed_in
    // -----------------------------------------------------------
    //
    // A local account's sign-out link posts to /user/logout;
    // an SSO account's to /user/logout_saml, which also ends
    // the VU SSO session.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_logout_link_matches_the_way_the_player_signed_in(): void
    {
        $this->signIn($this->makePlayer());
        $this->get('/user/leaderboard')
            ->assertSee("onClick=\"Post_Link('https://lab.knf.vu.lt/user/logout');\"", false)
            ->assertDontSee('/user/logout_saml', false);

        $this->signIn($this->makeSsoPlayer());
        $this->get('/user/leaderboard')->assertSee("onClick=\"Post_Link('https://lab.knf.vu.lt/user/logout_saml');\"", false);
    }






    // -----------------------------------------------------------
    // test_a_refused_form_keeps_no_password
    // -----------------------------------------------------------
    //
    // A refused login form comes back with the e-mail filled in
    // again, but the password typed into it is never kept in the
    // session.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_refused_form_keeps_no_password(): void
    {
        $this->post('/login', ['email' => 'jonas@players.test', 'password' => 'short']);

        $this->assertSame('jonas@players.test', session()->getOldInput('email'));
        $this->assertNull(session()->getOldInput('password'));
    }






    // -----------------------------------------------------------
    // test_signing_out_is_never_a_plain_link
    // -----------------------------------------------------------
    //
    // Opened as a link — which another site can make a
    // signed-in player follow — both sign-out addresses answer
    // 405, and the player stays signed in.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_signing_out_is_never_a_plain_link(): void
    {
        $player = $this->makePlayer();
        $this->signIn($player);

        $this->get('/user/logout')->assertStatus(405);
        $this->get('/user/logout_saml')->assertStatus(405);

        $this->assertAuthenticatedAs($player, 'users');
    }






    // -----------------------------------------------------------
    // test_the_admin_login_turns_away_only_a_signed_in_admin
    // -----------------------------------------------------------
    //
    // The control panel's login page sends a signed-in admin on
    // to the admin dashboard, but shows a signed-in player its
    // VU SSO button: a lab teacher trying the challenges as a
    // player still reaches the panel's login.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_admin_login_turns_away_only_a_signed_in_admin(): void
    {
        $this->signIn($this->makePlayer());
        $this->get('/control-panel')->assertOk()->assertSee('Login with VU SSO');

        $this->signInAdmin($this->makeAdmin());
        $this->get('/control-panel')->assertRedirect('/control-panel/dashboard');
    }






    // -----------------------------------------------------------
    // test_the_login_form_resumes_only_a_player_page
    // -----------------------------------------------------------
    //
    // The login form goes on to the page asked for before it
    // only when that page is one of the player side's: a page of
    // another site — a refused POST keeps the address it came
    // from — or of the control panel leads to the dashboard,
    // and either way the page is taken out of the session.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_login_form_resumes_only_a_player_page(): void
    {
        $player = $this->makePlayer();
        $form = ['email' => $player->email, 'password' => self::PASSWORD];

        $this->withSession(['url.intended' => 'https://elsewhere.example/user/login'])
            ->post('/login', $form)
            ->assertRedirect('/user/dashboard');
        $this->assertNull(session('url.intended'));

        $this->post('/user/logout');
        $this->withSession(['url.intended' => 'https://lab.knf.vu.lt/control-panel/users'])
            ->post('/login', $form)
            ->assertRedirect('/user/dashboard');
        $this->assertNull(session('url.intended'));
    }






    // -----------------------------------------------------------
    // test_signing_out_forgets_the_page_asked_for
    // -----------------------------------------------------------
    //
    // Signing out takes the page asked for before a login out of
    // the session, so the next person to sign in on a shared
    // computer is not sent to it.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_signing_out_forgets_the_page_asked_for(): void
    {
        $this->signIn($this->makePlayer())
            ->withSession(['url.intended' => 'https://lab.knf.vu.lt/user/leaderboard'])
            ->post('/user/logout')
            ->assertRedirect('/');

        $this->assertNull(session('url.intended'));
    }






    // -----------------------------------------------------------
    // test_an_account_switched_off_is_signed_out_at_its_next_request
    // -----------------------------------------------------------
    //
    // A player signed in when an admin switches the account off
    // is signed out at the next request, sent to the login page
    // with the message the login form gives an inactive account,
    // and the page is not served.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_account_switched_off_is_signed_out_at_its_next_request(): void
    {
        $player = $this->makePlayer();
        $this->signIn($player)->get('/user/leaderboard')->assertOk();

        $this->signInAdmin($this->makeAdmin(true))->post("/control-panel/users/status/{$player->id}/1");
        // The guard keeps the instance it was given, while a real
        // request loads the account anew
        $player->refresh();

        $this->get('/user/leaderboard')->assertRedirect('/');
        $this->assertErrorFlashed('Your account is not active');
        $this->assertGuest('users');
    }






    // -----------------------------------------------------------
    // test_an_account_switched_off_gets_no_verification_link
    // -----------------------------------------------------------
    //
    // Before 2026-10-23 the control panel's status switch left
    // verify_token in place, so an account that was signed up,
    // switched on and later switched off still holds one. As
    // it has signed in before, its login reads as switched
    // off, not as never verified: it gets "Your account is not
    // active" and no fresh link that would switch it back on.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_account_switched_off_gets_no_verification_link(): void
    {
        Mail::fake();
        $player = $this->makePlayer([
            'status'       => 0,
            'verify_token' => hash('sha256', 'left-by-the-old-switch'),
            'last_login'   => '2026-09-01 10:00:00',
        ]);

        $this->post('/login', ['email' => $player->email, 'password' => self::PASSWORD])->assertRedirect('/');

        Mail::assertNotSent(UserVerifyMail::class);
        $this->assertErrorFlashed('Your account is not active');
    }






    // -----------------------------------------------------------
    // test_a_login_never_resumes_a_dialog
    // -----------------------------------------------------------
    //
    // A guest bounced from the feedback dialog, which the
    // player pages load into a modal, has the dialog's address
    // kept as the page asked for. The login goes on to the
    // dashboard instead, as the dialog alone would be a bare
    // form.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_login_never_resumes_a_dialog(): void
    {
        $player = $this->makePlayer();
        $this->get('/user/dialog/feedback', ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html, */*; q=0.01'])
            ->assertRedirect('/');

        $this->post('/login', ['email' => $player->email, 'password' => self::PASSWORD])->assertRedirect('/user/dashboard');
    }






    // -----------------------------------------------------------
    // test_a_sign_out_from_an_expired_page_is_offered_again
    // -----------------------------------------------------------
    //
    // The plain sign-out of a local account answers a refused
    // token the same way: the page offers the sign-out again,
    // and pressed, it signs the player out.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_sign_out_from_an_expired_page_is_offered_again(): void
    {
        $this->app['env'] = 'production';
        $this->signIn($this->makePlayer());
        $this->startSession();
        $stale = csrf_token();
        $this->app['session']->regenerateToken();

        $retry = $this->post('/user/logout', ['_token' => $stale]);

        $retry->assertStatus(419);
        $retry->assertSee('action="https://lab.knf.vu.lt/user/logout"', false);
        $this->assertSame(1, preg_match('/name="_token" value="([^"]+)"/', $retry->getContent(), $token));
        $this->post('/user/logout', ['_token' => $token[1]])->assertRedirect('/');
        $this->assertGuest('users');
    }






    // -----------------------------------------------------------
    // test_a_sign_out_pressed_again_leaves_no_page_to_resume
    // -----------------------------------------------------------
    //
    // A sign-out sent from a page whose session has ended is
    // offered again, and pressed again the guard refuses it,
    // as the session is gone; Laravel then keeps the
    // sign-out's own address as the page asked for. The next
    // login goes on to the dashboard all the same, as a login
    // resumes no address a GET cannot open.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_sign_out_pressed_again_leaves_no_page_to_resume(): void
    {
        $this->app['env'] = 'production';
        $player = $this->makePlayer();
        $this->signIn($player);
        $this->startSession();
        $stale = csrf_token();
        Auth::guard('users')->logout();
        $this->app['session']->regenerateToken();

        // The page answering the refused sign-out offers it again,
        // and its button stores the sign-out as the page to resume
        $retry = $this->post('/user/logout', ['_token' => $stale]);
        $retry->assertStatus(419);
        $this->assertSame(1, preg_match('/name="_token" value="([^"]+)"/', $retry->getContent(), $token));
        $this->from('https://lab.knf.vu.lt/user/logout')->post('/user/logout', ['_token' => $token[1]]);
        $this->assertStringEndsWith('/user/logout', (string) session('url.intended'));

        $this->post('/login', ['email' => $player->email, 'password' => self::PASSWORD, '_token' => csrf_token()])
            ->assertRedirect('/user/dashboard');
    }






    // -----------------------------------------------------------
    // test_the_session_cookie_travels_over_https_only
    // -----------------------------------------------------------
    //
    // The session cookie carries the Secure flag, as the site
    // is served over https only: a browser never sends it on a
    // plain http request.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_session_cookie_travels_over_https_only(): void
    {
        $cookie = $this->get('/')->getCookie(config('session.cookie'), false);

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isSecure());
    }






    // -----------------------------------------------------------
    // test_a_refused_form_keeps_only_the_email
    // -----------------------------------------------------------
    //
    // A refused login form refills the e-mail alone, and only
    // as text of at most 255 characters: no field a hand-made
    // request adds, and no megabyte-long e-mail, is kept in
    // the session, which Redis holds for 36 hours.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_refused_form_keeps_only_the_email(): void
    {
        $this->post('/login', ['email' => 'not-an-address', 'password' => 'short', 'padding' => str_repeat('x', 1000000)]);

        $this->assertSame(['email' => 'not-an-address'], session()->getOldInput());
        $this->assertLessThan(65536, strlen(serialize(session()->all())));

        session()->flush();
        $this->post('/login', ['email' => str_repeat('a', 1000000) . '@players.test', 'password' => 'short']);

        $this->assertSame([], session()->getOldInput());
    }






    // -----------------------------------------------------------
    // test_a_form_refused_with_a_list_for_a_field_shows_again
    // -----------------------------------------------------------
    //
    // A login sent with a list for the e-mail — only a
    // hand-made request sends one — is refused, and the login
    // page shows again with the box empty, not a 500.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_form_refused_with_a_list_for_a_field_shows_again(): void
    {
        $this->from('/')->post('/login', ['email' => ['jonas@players.test'], 'password' => 'long-enough-1']);

        $page = $this->get('/');

        $page->assertOk();
        $page->assertSee('name="email" type="email" value=""', false);
    }






    // -----------------------------------------------------------
    // test_an_error_message_never_takes_the_place_of_its_label
    // -----------------------------------------------------------
    //
    // The theme's front.js floats a box's label as the last
    // element of the box's group, so on the login and reminder
    // pages a field's error message comes before its label,
    // never after it, where front.js would take the message
    // for the label.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_error_message_never_takes_the_place_of_its_label(): void
    {
        $this->from('/')->post('/login', ['email' => 'jonas@players.test', 'password' => 'short']);
        $login = $this->get('/')->getContent();
        $this->from('/forgot-password')->post('/forgot-password', ['email' => 'not-an-address']);
        $reminder = $this->get('/forgot-password')->getContent();

        foreach (['the login page' => $login, 'the reminder page' => $reminder] as $page => $html) {
            $this->assertStringContainsString('class="text-danger"', $html, "{$page} shows no error");
            preg_match_all('#<div class="input-material-group[^"]*">(.*?)</div>#s', $html, $groups);
            foreach ($groups[1] as $group) {
                if (str_contains($group, 'class="input-material"')) {
                    $this->assertStringEndsWith('</label>', trim($group), "on {$page} a field's error message sits where front.js looks for its label");
                }
            }
        }
    }






    // -----------------------------------------------------------
    // test_a_wrong_password_keeps_the_email_typed
    // -----------------------------------------------------------
    //
    // A login refused for its credentials comes back with the
    // e-mail typed, so a player who mistyped the password types
    // only the password again.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_wrong_password_keeps_the_email_typed(): void
    {
        $player = $this->makePlayer();

        $this->from('/')->post('/login', ['email' => $player->email, 'password' => 'not-the-password-1']);

        $this->assertErrorFlashed('Wrong login credentials');
        $this->assertSame($player->email, session()->getOldInput('email'));
        $this->assertNull(session()->getOldInput('password'));
    }






    // -----------------------------------------------------------
    // test_no_other_vu_host_can_set_the_session_cookie
    // -----------------------------------------------------------
    //
    // The session cookie's name carries the __Host- prefix,
    // and the cookie is Secure, for the whole site, without a
    // domain: a browser takes such a cookie from the lab's own
    // host alone, so no other host under vu.lt can plant a
    // session of its choice on a player.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_no_other_vu_host_can_set_the_session_cookie(): void
    {
        $cookie = $this->get('/')->getCookie(config('session.cookie'), false);

        $this->assertNotNull($cookie);
        $this->assertStringStartsWith('__Host-', $cookie->getName());
        $this->assertTrue($cookie->isSecure());
        $this->assertSame('/', $cookie->getPath());
        $this->assertNull($cookie->getDomain());
    }






    // -----------------------------------------------------------
    // test_a_new_password_ends_the_accounts_other_sessions
    // -----------------------------------------------------------
    //
    // A session signed in before the account's password
    // changed — a reset, or a main admin's new password — is
    // signed out at its next page and sent to the login page,
    // so a session left open on a lab computer does not
    // outlive the change.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_new_password_ends_the_accounts_other_sessions(): void
    {
        $player = $this->makePlayer();
        $this->signIn($player)->get('/user/leaderboard')->assertOk();

        $player->update(['password' => Hash::make('a-brand-new-password-1')]);
        $response = $this->get('/user/leaderboard');

        $response->assertRedirect('/');
        $this->assertGuest('users');
    }






    // -----------------------------------------------------------
    // test_a_session_without_a_password_change_stays
    // -----------------------------------------------------------
    //
    // The check of the password ends nothing else: a session
    // whose account keeps its password stays signed in page
    // after page.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_session_without_a_password_change_stays(): void
    {
        $player = $this->makePlayer();

        $this->signIn($player)->get('/user/leaderboard')->assertOk();
        $this->get('/user/leaderboard')->assertOk();

        $this->assertAuthenticatedAs($player, 'users');
    }






    // -----------------------------------------------------------
    // test_a_login_ends_on_the_dashboard_whatever_address_was_asked_for
    // -----------------------------------------------------------
    //
    // A page asked for before the login whose address Symfony
    // refuses to parse — a backslash in its path, which only a
    // hand-made request leaves — is not resumed: the login ends
    // on the dashboard, not on a 400.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_login_ends_on_the_dashboard_whatever_address_was_asked_for(): void
    {
        $player = $this->makePlayer();

        $response = $this->withSession(['url.intended' => url('/user/challenge/a\\b')])
            ->from('/')
            ->post('/login', ['email' => $player->email, 'password' => self::PASSWORD]);

        $response->assertRedirect('/user/dashboard');
        $this->assertAuthenticatedAs($player, 'users');
    }
}
