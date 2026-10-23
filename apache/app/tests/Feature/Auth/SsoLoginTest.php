<?php

// -----------------------------------------------------------
//  [*] SsoLoginTest — the VU SSO routes, end to end
//
//  /cp signs a player in, /control-panel/login an admin, and
//  the two logout routes sign them out and hand over to the
//  identity provider's own logout. All four build a
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
    // The SSO logout ends the lab session and redirects to the
    // identity provider's logout, which returns to the front
    // page of the lab.
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

        $this->get('/user/logout_saml')->assertRedirect('https://idp.sso.test/logout?ReturnTo=lab');

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
    // The admin logout ends the admin session and redirects to
    // the identity provider's logout, which returns to the
    // admin login page.
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

        $this->get('/control-panel/logout')->assertRedirect('https://idp.sso.test/logout?ReturnTo=cp');

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
}
