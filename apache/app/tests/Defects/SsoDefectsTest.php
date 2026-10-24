<?php

// -----------------------------------------------------------
//  [*] SsoDefectsTest — open defects of the VU single sign-on
//
//  Each test states the behaviour the lab wants where the
//  SSO login or logout falls short today, under a LAB-nn id;
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
// LAB-41 and LAB-42.
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
    // test_lab_41_a_vu_login_never_resumes_a_dialog
    // -----------------------------------------------------------
    //
    // Known bug LAB-41 (low): a guest bounced from a dialog
    // the panel loads into its modal (dialogs.js, jQuery's
    // load) has the dialog's address kept as the page asked
    // for, as jQuery's request accepts HTML first and so does
    // not count as a JSON one. The VU login then resumes it,
    // as Controller::intendedWithin checks only that the
    // address lies under /control-panel or /user: an admin
    // whose session ran out, and who opened a dialog, lands on
    // a bare form without layout or menu. The same holds for
    // the player's feedback dialog. Fix: never resume an
    // address under /dialog/.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_41_a_vu_login_never_resumes_a_dialog(): void
    {
        $this->get('/control-panel/dialog/category/new', ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html, */*; q=0.01'])
            ->assertRedirect('/control-panel');
        $admin = $this->makeAdmin(true);
        $client = Mockery::mock('overload:SimpleSAML\Auth\Simple');
        $client->shouldReceive('requireAuth')->once();
        $client->shouldReceive('getAttributes')->andReturn([
            'urn:oid:0.9.2342.19200300.100.1.1' => [$admin->vu_id],
            'urn:oid:0.9.2342.19200300.100.1.3' => [$admin->email],
            'urn:oid:2.5.4.42'                  => ['Ona'],
            'urn:oid:2.5.4.4'                   => ['Onaitė'],
        ]);

        $response = $this->get('/control-panel/login');

        $this->assertDefectStillOpen('LAB-41', function () use ($response) {
            $response->assertRedirect('/control-panel/dashboard');
        });
    }






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
