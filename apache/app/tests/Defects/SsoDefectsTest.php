<?php

// -----------------------------------------------------------
//  [*] SsoDefectsTest — open defects of the VU single sign-on
//
//  Each test states the behaviour the lab wants where the
//  VU single sign-on falls short today, under a LAB-nn id;
//  KnownDefects reports it incomplete while the defect is
//  open and fails it once a fix lands. The banner of every
//  test gives the severity, the mechanism, naming its files
//  and methods, and the fix. As in Feature\Auth\SsoLoginTest,
//  the SimpleSAMLphp client is replaced by a Mockery
//  overload, so every test runs in a PHP process of its own.
//
//  Used by:
//    - PHPUnit — the Defects suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Defects;

use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\Support\KnownDefects;
use Tests\TestCase;








// -----------------------------------------------------------
// SsoDefectsTest
// -----------------------------------------------------------
//
// LAB-72 and LAB-76.
//
//   fakeIdentityProvider — overloads the SimpleSAMLphp client
//
// Used by:
//   - PHPUnit — the Defects suite (runTests.sh)
// -----------------------------------------------------------

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SsoDefectsTest extends TestCase
{

    // assertDefectStillOpen, which reports a test incomplete
    // while its defect is open
    use KnownDefects;






    // -----------------------------------------------------------
    // fakeIdentityProvider
    // -----------------------------------------------------------
    //
    // Replaces SimpleSAML\Auth\Simple for the rest of the
    // test: requireAuth lets every visitor through, as for a
    // VU sign-on already made, getAttributes returns the VU
    // user id and mail address given, keyed by OID like the
    // real ones, and isAuthenticated answers whether
    // SimpleSAMLphp still holds the sign-on, read from the
    // variable given each time it is asked.
    //
    // Used by:
    //   - the tests below
    // -----------------------------------------------------------

    private function fakeIdentityProvider(string $uid, string $mail, bool &$signedOn): MockInterface
    {
        $client = Mockery::mock('overload:SimpleSAML\Auth\Simple');
        $client->shouldReceive('requireAuth');
        $client->shouldReceive('isAuthenticated')->andReturnUsing(function () use (&$signedOn) {
            return $signedOn;
        });
        $client->shouldReceive('getAttributes')->andReturn([
            'urn:oid:0.9.2342.19200300.100.1.1' => [$uid],
            'urn:oid:0.9.2342.19200300.100.1.3' => [$mail],
            'urn:oid:2.5.4.42'                  => ['Ona'],
            'urn:oid:2.5.4.4'                   => ['Onaitė'],
        ]);
        return $client;
    }






    // -----------------------------------------------------------
    // test_lab_72_a_vu_sign_out_at_the_identity_provider_ends_the_lab_sessions
    // -----------------------------------------------------------
    //
    // Known bug LAB-72 (medium): a sign-out at sso.vu.lt — at
    // the identity provider itself, or in another VU service,
    // which it passes on to every service of the sign-on —
    // reaches the lab's SimpleSAMLphp, which ends its own
    // session and answers that the visitor is signed out (the
    // saml module's logout endpoint; with the phpsession store
    // it ends the session of the browser at hand), but nothing
    // tells the lab: SamlAuth and SamlAuthAdmin read
    // SimpleSAMLphp only at /cp and /control-panel/login, and
    // the users and admins guards never look again, so the lab
    // and control-panel sessions live on for their 36 hours,
    // renewed by every page. On a shared computer the next
    // person opens the lab, or the control panel, as the
    // student or teacher who signed out of VU believing it
    // signed them out everywhere. Here a VU user who is both a
    // player and an admin signs in on both sides, and
    // SimpleSAMLphp's session then ends. Fix: remember in the
    // session that a sign-in came through VU, and sign such a
    // session out on any page once SimpleSAMLphp no longer
    // holds the VU sign-on (Simple::isAuthenticated).
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_72_a_vu_sign_out_at_the_identity_provider_ends_the_lab_sessions(): void
    {
        $player = $this->makeSsoPlayer();
        $admin = $this->makeAdmin(true, ['vu_id' => $player->vu_id]);
        $signedOn = true;
        $this->fakeIdentityProvider($player->vu_id, $player->email, $signedOn);
        $this->get('/cp')->assertRedirect('/user/dashboard');
        $this->get('/control-panel/login')->assertRedirect('/control-panel/dashboard');
        $this->assertAuthenticatedAs($player, 'users');
        $this->assertAuthenticatedAs($admin, 'admins');

        $signedOn = false;
        $lab = $this->get('/user/dashboard');
        $panel = $this->get('/control-panel/dashboard');

        $this->assertDefectStillOpen('LAB-72', function () use ($lab, $panel) {
            $this->assertTrue($lab->isRedirect(), "the lab's pages open as the player signed out of VU");
            $this->assertTrue($panel->isRedirect(), 'the control panel opens as the admin signed out of VU');
            $this->assertGuest('users');
            $this->assertGuest('admins');
        });
    }






    // -----------------------------------------------------------
    // test_lab_76_a_refused_vu_login_leaves_nobody_signed_in
    // -----------------------------------------------------------
    //
    // Known bug LAB-76 (low): a VU login that maps to no
    // account it may use leaves the account signed in before
    // it in place. SamlAuthAdmin::handle hands check_admin's
    // null for a VU user who is no admin to loginUsingId,
    // which then finds nobody and changes nothing, so
    // AdminAuthController::cp_login goes on to the dashboard of
    // whichever admin was signed in on that browser; and
    // SamlAuth::handle turns an account switched off back to
    // the login page without signing anyone out, where the
    // guest middleware forwards to the earlier player's
    // dashboard and the "Your account is not active" message
    // is lost. Here an admin's and a player's sessions are open
    // in the browser, and a VU user who is no admin, and whose
    // player account is switched off, completes both VU
    // logins. Fix: sign the guard out when the VU user maps to
    // no account it may use.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_76_a_refused_vu_login_leaves_nobody_signed_in(): void
    {
        $outsider = $this->makeSsoPlayer(['status' => 0]);
        $signedOn = true;
        $this->fakeIdentityProvider($outsider->vu_id, $outsider->email, $signedOn);
        $this->actingAs($this->makeAdmin(true), 'admins');
        $this->actingAs($this->makePlayer(), 'users');

        $this->get('/control-panel/login');
        $this->get('/cp');

        $this->assertDefectStillOpen('LAB-76', function () {
            $this->assertGuest('admins');
            $this->assertGuest('users');
        });
    }
}
