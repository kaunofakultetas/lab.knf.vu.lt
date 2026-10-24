<?php

// -----------------------------------------------------------
//  [*] SsoLoginTest — the VU SSO routes, end to end
//
//  /cp signs a player in, /control-panel/login an admin —
//  each going on to the page of its side asked for before
//  the login — and the two logout routes sign them out and
//  hand over to the identity provider's own logout through a
//  page that moves on by itself. All four build a
//  SimpleSAMLphp client for the "default-sp" service
//  provider, which needs the SSO configuration and the VU
//  identity provider — neither exists in the tests
//  container. So each test replaces the client class itself
//  with a Mockery overload that hands out the SAML attributes
//  the test chooses, and the middleware, controllers and
//  provisioning around it run for real.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;








// -----------------------------------------------------------
// SsoLoginTest
// -----------------------------------------------------------
//
// An overload can only replace a class PHP has not loaded
// yet, so every test runs in a PHP process of its own.
//
//   fakeIdentityProvider — overloads the SimpleSAMLphp client
//
// Used by:
//   - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SsoLoginTest extends TestCase
{






    // -----------------------------------------------------------
    // fakeIdentityProvider
    // -----------------------------------------------------------
    //
    // Replaces SimpleSAML\Auth\Simple for the rest of the
    // test: requireAuth must be called exactly once — the
    // middleware may only read attributes of an authenticated
    // visitor — and getAttributes returns the VU user id and
    // mail address given, keyed by OID like the real ones.
    //
    // Used by:
    //   - the sign-in tests below
    // -----------------------------------------------------------

    private function fakeIdentityProvider(string $uid, string $mail): MockInterface
    {
        $client = Mockery::mock('overload:SimpleSAML\Auth\Simple');
        $client->shouldReceive('requireAuth')->once();
        $client->shouldReceive('getAttributes')->andReturn([
            'urn:oid:0.9.2342.19200300.100.1.1' => [$uid],
            'urn:oid:0.9.2342.19200300.100.1.3' => [$mail],
            'urn:oid:2.5.4.42'                  => ['Ona'],
            'urn:oid:2.5.4.4'                   => ['Onaitė'],
        ]);
        return $client;
    }






    // -----------------------------------------------------------
    // test_a_first_sso_visit_creates_the_account_and_signs_in
    // -----------------------------------------------------------
    //
    // A VU user new to the lab is provisioned, signed in on
    // the users guard and sent to the dashboard.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_first_sso_visit_creates_the_account_and_signs_in(): void
    {
        $this->fakeIdentityProvider('s2200001', 'ona@sso.test');

        $this->get('/cp')->assertRedirect('/user/dashboard');

        $player = User::where('vu_id', 's2200001')->firstOrFail();
        $this->assertAuthenticatedAs($player, 'users');
        $this->assertSame('Ona Onaitė', $player->display_name);
    }






    // -----------------------------------------------------------
    // test_a_returning_vu_user_is_signed_into_the_same_account
    // -----------------------------------------------------------
    //
    // A known VU user is signed into the account it already
    // has, points and all, and no second account appears.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_returning_vu_user_is_signed_into_the_same_account(): void
    {
        $player = $this->makeSsoPlayer(['points' => 120]);
        $this->fakeIdentityProvider($player->vu_id, $player->email);

        $this->get('/cp')->assertRedirect('/user/dashboard');

        $this->assertAuthenticatedAs($player, 'users');
        $this->assertSame(1, User::where('vu_id', $player->vu_id)->count());
    }






    // -----------------------------------------------------------
    // test_sso_logout_hands_over_to_the_identity_provider
    // -----------------------------------------------------------
    //
    // The SSO logout ends the lab session and hands over to the
    // identity provider's logout through a page that moves on
    // by itself, a meta refresh, never through a redirect of
    // the sign-out form: the site's CSP lets forms reach the
    // site only, and browsers hold every redirect of a form's
    // navigation to that, so a redirect to the identity
    // provider is blocked. The provider's logout returns to
    // the front page of the lab.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_sso_logout_hands_over_to_the_identity_provider(): void
    {
        $client = Mockery::mock('overload:SimpleSAML\Auth\Simple');
        $client->shouldReceive('getLogoutURL')
            ->with('https://lab.knf.vu.lt/')
            ->andReturn('https://idp.sso.test/logout?ReturnTo=lab');
        $this->signIn($this->makeSsoPlayer());

        $response = $this->post('/user/logout_saml');

        $response->assertOk();
        $response->assertSee('<meta http-equiv="refresh" content="0;url=https://idp.sso.test/logout?ReturnTo=lab">', false);
        $response->assertSee('href="https://idp.sso.test/logout?ReturnTo=lab"', false);
        $this->assertGuest('users');
    }






    // -----------------------------------------------------------
    // test_an_admin_signs_into_the_control_panel_through_the_sso
    // -----------------------------------------------------------
    //
    // A VU user listed as an admin is signed in on the admins
    // guard with its main flag in the session, and lands on
    // the admin dashboard.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_admin_signs_into_the_control_panel_through_the_sso(): void
    {
        $admin = $this->makeAdmin(true);
        $this->fakeIdentityProvider($admin->vu_id, $admin->email);

        $this->get('/control-panel/login')->assertRedirect('/control-panel/dashboard');

        $this->assertAuthenticatedAs($admin, 'admins');
        $this->assertSame(1, session('main'));
        $this->get('/control-panel/dashboard')->assertOk();
    }






    // -----------------------------------------------------------
    // test_a_vu_user_who_is_no_admin_stays_outside
    // -----------------------------------------------------------
    //
    // Passing the SSO is not enough for the control panel: a
    // VU user without an admin row ends up on the admin login
    // page again, signed into nothing.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_vu_user_who_is_no_admin_stays_outside(): void
    {
        $this->fakeIdentityProvider('s2200002', 'jonas@sso.test');

        $this->get('/control-panel/login')->assertRedirect('/control-panel/dashboard');

        $this->assertGuest('admins');
        $this->assertGuest('users');
        $this->get('/control-panel/dashboard')->assertRedirect('/control-panel');
    }






    // -----------------------------------------------------------
    // test_admin_logout_hands_over_to_the_identity_provider
    // -----------------------------------------------------------
    //
    // The admin logout ends the admin session and hands over
    // to the identity provider's logout the same way, through
    // a page that moves on by itself, as a redirect of the
    // sign-out form would be blocked; the provider returns to
    // the admin login page.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_admin_logout_hands_over_to_the_identity_provider(): void
    {
        $client = Mockery::mock('overload:SimpleSAML\Auth\Simple');
        $client->shouldReceive('getLogoutURL')
            ->with('https://lab.knf.vu.lt/control-panel')
            ->andReturn('https://idp.sso.test/logout?ReturnTo=cp');
        $this->signInAdmin($this->makeAdmin());

        $response = $this->post('/control-panel/logout');

        $response->assertOk();
        $response->assertSee('<meta http-equiv="refresh" content="0;url=https://idp.sso.test/logout?ReturnTo=cp">', false);
        $this->assertGuest('admins');
    }






    // -----------------------------------------------------------
    // test_a_taken_username_does_not_block_a_vu_login
    // -----------------------------------------------------------
    //
    // A local player who registered a VU user id as username does
    // not lock that VU user out: the first SSO login provisions
    // the account under the first free numbered username and
    // signs it in.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_taken_username_does_not_block_a_vu_login(): void
    {
        $this->makePlayer(['username' => 's7777777']);
        $this->fakeIdentityProvider('s7777777', 'ona@sso.test');

        $response = $this->get('/cp');

        $response->assertRedirect('/user/dashboard');
        $this->assertSame('s7777777_2', User::where('vu_id', 's7777777')->value('username'));
        $this->assertAuthenticated('users');
    }






    // -----------------------------------------------------------
    // test_a_deactivated_vu_account_stays_outside
    // -----------------------------------------------------------
    //
    // A VU account switched inactive in the control panel is
    // turned back to the login page with the message the
    // login form gives an inactive account, signed in to
    // nothing, and the refused login is not recorded.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_deactivated_vu_account_stays_outside(): void
    {
        $player = $this->makeSsoPlayer(['status' => 0]);
        $this->fakeIdentityProvider($player->vu_id, $player->email);

        $response = $this->get('/cp');

        $response->assertRedirect('/');
        $this->assertErrorFlashed('Your account is not active');
        $this->assertGuest('users');
        $this->assertNull($player->refresh()->last_login);
    }






    // -----------------------------------------------------------
    // test_a_vu_login_resumes_the_player_page_asked_for
    // -----------------------------------------------------------
    //
    // A guest sent to the login from a player page — a challenge
    // link a teacher shared, say — lands on that page after the
    // VU login, and the page is taken out of the session, so no
    // later redirect finds it again.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_vu_login_resumes_the_player_page_asked_for(): void
    {
        $this->fakeIdentityProvider('s1234567', 'ona@stud.vu.lt');

        $this->withSession(['url.intended' => 'https://lab.knf.vu.lt/user/leaderboard'])
            ->get('/cp')
            ->assertRedirect('https://lab.knf.vu.lt/user/leaderboard');

        $this->assertNull(session('url.intended'));
    }






    // -----------------------------------------------------------
    // test_a_vu_login_resumes_no_page_of_the_panel
    // -----------------------------------------------------------
    //
    // A page of the control panel asked for earlier is no page
    // for a player: the VU login goes to the dashboard instead,
    // and the page is taken out of the session all the same.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_vu_login_resumes_no_page_of_the_panel(): void
    {
        $this->fakeIdentityProvider('s1234567', 'ona@stud.vu.lt');

        $this->withSession(['url.intended' => 'https://lab.knf.vu.lt/control-panel/users'])
            ->get('/cp')
            ->assertRedirect('/user/dashboard');

        $this->assertNull(session('url.intended'));
    }






    // -----------------------------------------------------------
    // test_an_admin_login_resumes_the_panel_page_asked_for
    // -----------------------------------------------------------
    //
    // The admin's VU login goes on to the control-panel page
    // asked for before it, and takes the page out of the
    // session.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_admin_login_resumes_the_panel_page_asked_for(): void
    {
        $admin = $this->makeAdmin(true);
        $this->fakeIdentityProvider($admin->vu_id, $admin->email);

        $this->withSession(['url.intended' => 'https://lab.knf.vu.lt/control-panel/users/vu-groups'])
            ->get('/control-panel/login')
            ->assertRedirect('https://lab.knf.vu.lt/control-panel/users/vu-groups');

        $this->assertNull(session('url.intended'));
    }






    // -----------------------------------------------------------
    // test_a_vu_login_never_resumes_a_dialog
    // -----------------------------------------------------------
    //
    // A guest bounced from a dialog the panel loads into its
    // modal has the dialog's address kept as the page asked
    // for, as jQuery's request accepts HTML first. The admin's
    // VU login goes on to the dashboard instead of that
    // address, which would show a bare form without layout or
    // menu.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_vu_login_never_resumes_a_dialog(): void
    {
        $this->get('/control-panel/dialog/category/new', ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html, */*; q=0.01'])
            ->assertRedirect('/control-panel');
        $admin = $this->makeAdmin(true);
        $this->fakeIdentityProvider($admin->vu_id, $admin->email);

        $this->get('/control-panel/login')->assertRedirect('/control-panel/dashboard');
    }






    // -----------------------------------------------------------
    // test_a_logout_from_an_expired_page_still_signs_out_of_vu
    // -----------------------------------------------------------
    //
    // A sign-out sent from a page whose token no longer
    // matches — a long-open tab, or a session lost to a stack
    // update — is refused, and the answer offers the sign-out
    // again under the session's current token. Pressed, it
    // goes on to the VU logout, so the single sign-on still
    // ends: on a shared computer the next "Login with VU SSO"
    // would otherwise sign in as the same student.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_logout_from_an_expired_page_still_signs_out_of_vu(): void
    {
        // In the production environment Laravel logs PHP's
        // deprecation notices, which loads its logger, whose code
        // PHP 8.4 reports as deprecated while compiling it; an
        // isolated process would print that and fail on it
        ini_set('display_errors', '0');
        $this->app['env'] = 'production';
        $client = Mockery::mock('overload:SimpleSAML\Auth\Simple');
        $client->shouldReceive('getLogoutURL')->andReturn('https://idp.sso.test/logout?ReturnTo=lab');
        $this->signIn($this->makeSsoPlayer());
        $this->startSession();
        $stale = csrf_token();
        $this->app['session']->regenerateToken();

        $retry = $this->post('/user/logout_saml', ['_token' => $stale]);

        $retry->assertStatus(419);
        $retry->assertSee('action="https://lab.knf.vu.lt/user/logout_saml"', false);
        $this->assertSame(1, preg_match('/name="_token" value="([^"]+)"/', $retry->getContent(), $token));
        $this->post('/user/logout_saml', ['_token' => $token[1]])
            ->assertOk()
            ->assertSee('href="https://idp.sso.test/logout?ReturnTo=lab"', false);
        $this->assertGuest('users');
    }






    // -----------------------------------------------------------
    // test_an_admin_logout_from_an_expired_page_still_signs_out_of_vu
    // -----------------------------------------------------------
    //
    // The control panel's sign-out works the same way, and
    // asks for no admin session: once the admin's session has
    // ended, the sign-out offered again still reaches the VU
    // logout instead of the panel's login page.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_admin_logout_from_an_expired_page_still_signs_out_of_vu(): void
    {
        // In the production environment Laravel logs PHP's
        // deprecation notices, which loads its logger, whose code
        // PHP 8.4 reports as deprecated while compiling it; an
        // isolated process would print that and fail on it
        ini_set('display_errors', '0');
        $this->app['env'] = 'production';
        $client = Mockery::mock('overload:SimpleSAML\Auth\Simple');
        $client->shouldReceive('getLogoutURL')->andReturn('https://idp.sso.test/logout?ReturnTo=panel');
        $this->signInAdmin($this->makeAdmin(true));
        $this->startSession();
        $stale = csrf_token();
        Auth::guard('admins')->logout();
        $this->app['session']->regenerateToken();

        $retry = $this->post('/control-panel/logout', ['_token' => $stale]);

        $retry->assertStatus(419);
        $retry->assertSee('action="https://lab.knf.vu.lt/control-panel/logout"', false);
        $this->assertSame(1, preg_match('/name="_token" value="([^"]+)"/', $retry->getContent(), $token));
        $this->post('/control-panel/logout', ['_token' => $token[1]])
            ->assertOk()
            ->assertSee('href="https://idp.sso.test/logout?ReturnTo=panel"', false);
    }
}
