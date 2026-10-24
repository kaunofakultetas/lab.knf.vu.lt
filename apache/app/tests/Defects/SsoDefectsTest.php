<?php

// -----------------------------------------------------------
//  [*] SsoDefectsTest — open defects of the VU single sign-on
//
//  Each test states the behaviour the lab wants where the
//  SSO logout falls short today, under a LAB-nn id;
//  KnownDefects reports it incomplete while the defect is
//  open and fails it once a fix lands. The tests replace the
//  SimpleSAMLphp client with a Mockery overload, as
//  Feature\Auth\SsoLoginTest does, so each runs in a PHP
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
// LAB-42.
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
    // test_lab_42_a_logout_from_an_expired_page_still_signs_out_of_vu
    // -----------------------------------------------------------
    //
    // Known bug LAB-42 (low): the sign-outs are POSTs behind
    // the CSRF check (the logout routes of routes/web.php).
    // When the lab session behind the page has ended — a
    // long-open tab, or Redis emptied by a stack update — the
    // page's token no longer matches, the check answers "Page
    // Expired", and the VU single sign-on is never ended: on a
    // shared computer the next "Login with VU SSO" may sign in
    // as the previous user. Fix: answer a logout whose token
    // failed with a page that offers the sign-out again under
    // a fresh token, or carries on to the VU logout.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_42_a_logout_from_an_expired_page_still_signs_out_of_vu(): void
    {
        // The refused request loads Laravel's logger, whose code
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

        $body = $this->post('/user/logout_saml', ['_token' => $stale])->getContent();

        $this->assertDefectStillOpen('LAB-42', function () use ($body) {
            $this->assertTrue(
                str_contains($body, 'https://idp.sso.test/logout?ReturnTo=lab') || str_contains($body, 'action="https://lab.knf.vu.lt/user/logout_saml"'),
                'the expired page neither carries on to the VU logout nor offers the sign-out again'
            );
        });
    }
}
