<?php

// -----------------------------------------------------------
//  [*] SsoDefectsTest — open defects in the VU SSO login
//
//  Each test states the behaviour the lab wants where the SSO
//  login falls short today, under a LAB-nn id; KnownDefects
//  reports it incomplete while the defect is open and fails
//  it once a fix lands. Like SsoLoginTest, every test
//  overloads the SimpleSAMLphp client, and so runs in a PHP
//  process of its own.
//
//  Used by:
//    - PHPUnit — the Defects suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Defects;

use Mockery;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\Support\KnownDefects;
use Tests\TestCase;








// -----------------------------------------------------------
// SsoDefectsTest
// -----------------------------------------------------------
//
// LAB-15.
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

    use KnownDefects;






    // -----------------------------------------------------------
    // fakeIdentityProvider
    // -----------------------------------------------------------
    //
    // Replaces SimpleSAML\Auth\Simple for the rest of the
    // test with one that authenticates at once and releases
    // the VU user id and mail address given.
    //
    // Used by:
    //   - the tests below
    // -----------------------------------------------------------

    private function fakeIdentityProvider(string $uid, string $mail): void
    {
        $client = Mockery::mock('overload:SimpleSAML\Auth\Simple');
        $client->shouldReceive('requireAuth');
        $client->shouldReceive('getAttributes')->andReturn([
            'urn:oid:0.9.2342.19200300.100.1.1' => [$uid],
            'urn:oid:0.9.2342.19200300.100.1.3' => [$mail],
            'urn:oid:2.5.4.42'                  => ['Ona'],
            'urn:oid:2.5.4.4'                   => ['Onaitė'],
        ]);
    }






    // -----------------------------------------------------------
    // test_lab_15_a_deactivated_vu_account_stays_outside
    // -----------------------------------------------------------
    //
    // Known bug LAB-15 (medium): switching a VU account
    // inactive in the control panel does not lock it out —
    // the SSO login signs it in all the same. The saml_auth
    // middleware (SamlAuth::handle) signs in whatever id
    // UserAuthController::check_member returns, and neither
    // looks at the status the local login form checks. Fix:
    // refuse inactive accounts in check_member or the
    // middleware.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_15_a_deactivated_vu_account_stays_outside(): void
    {
        $player = $this->makeSsoPlayer(['status' => 0]);
        $this->fakeIdentityProvider($player->vu_id, $player->email);

        $this->get('/cp');

        $this->assertDefectStillOpen('LAB-15', function () {
            $this->assertGuest('users');
        });
    }
}
