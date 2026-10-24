<?php

// -----------------------------------------------------------
//  [*] SsoDefectsTest — open defects of the VU single sign-on
//
//  Each test states the behaviour the lab wants where the
//  VU single sign-on falls short today, under a LAB-nn id;
//  KnownDefects reports it incomplete while the defect is
//  open and fails it once a fix lands. The banner of every
//  test gives the severity, the mechanism, naming its files
//  and methods, and the fix.
//
//  Used by:
//    - PHPUnit — the Defects suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Defects;

use Tests\Support\KnownDefects;
use Tests\TestCase;








// -----------------------------------------------------------
// SsoDefectsTest
// -----------------------------------------------------------
//
// LAB-54.
//
// Used by:
//   - PHPUnit — the Defects suite (runTests.sh)
// -----------------------------------------------------------

final class SsoDefectsTest extends TestCase
{

    // assertDefectStillOpen, which reports a test incomplete
    // while its defect is open
    use KnownDefects;






    // -----------------------------------------------------------
    // test_lab_54_php_keeps_a_vu_sign_on_as_long_as_simplesamlphp_does
    // -----------------------------------------------------------
    //
    // Known bug LAB-54 (medium): the VU sign-outs
    // (UserAuthController::logout_saml,
    // AdminAuthController::logout) hand the browser to
    // SimpleSAMLphp's logout, which tells sso.vu.lt only while
    // SimpleSAMLphp still holds the visitor's session; without
    // one it just returns to the lab, and the VU single
    // sign-on stays open, so on a shared computer the next
    // "Login with VU SSO" signs in as the same student.
    // SimpleSAMLphp keeps that session in a PHP session file
    // under /tmp (store.type phpsession in the host's _SSO
    // config), written at the VU login and not touched again,
    // and the image sets no session settings of its own: PHP's
    // garbage collection, run on one session start in a
    // hundred, deletes the file once it is 24 minutes old
    // (session.gc_maxlifetime 1440). /tmp is also a tmpfs that
    // every redeploy of the container starts empty, and
    // SimpleSAMLphp ends its own session after 8 hours while
    // the lab's lasts 36 (SESSION_LIFETIME is set twice in
    // apache/app/.env, and the later 2160 minutes wins). Here
    // the image's PHP must keep a session file for
    // SimpleSAMLphp's 8 hours at least. Fix: set
    // session.gc_maxlifetime to 8 hours or more in an ini file
    // of the image, keep SimpleSAMLphp's sessions where a
    // redeploy does not empty them (its redis store, or a
    // mounted folder), and let a sign-out that finds no
    // SimpleSAMLphp session still send the browser to the
    // identity provider's own logout.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_54_php_keeps_a_vu_sign_on_as_long_as_simplesamlphp_does(): void
    {
        $this->assertDefectStillOpen('LAB-54', function () {
            $this->assertGreaterThanOrEqual(8 * 3600, (int) ini_get('session.gc_maxlifetime'), 'PHP deletes a VU sign-on after 24 minutes');
        });
    }
}
