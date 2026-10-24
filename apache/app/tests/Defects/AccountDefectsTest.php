<?php

// -----------------------------------------------------------
//  [*] AccountDefectsTest — open defects of the account flows
//
//  Each test states the behaviour the lab wants where the
//  registration and reminder forms fall short today, under a
//  LAB-nn id; KnownDefects reports it incomplete while the
//  defect is open and fails it once a fix lands. The banner
//  of every test gives the severity, the mechanism, naming
//  its files and methods, and the fix. The tests that
//  replace the SimpleSAMLphp client are SsoDefectsTest's.
//
//  Used by:
//    - PHPUnit — the Defects suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Defects;

use Tests\Support\KnownDefects;
use Tests\TestCase;








// -----------------------------------------------------------
// AccountDefectsTest
// -----------------------------------------------------------
//
// LAB-40.
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
    // test_lab_40_a_refused_recaptcha_says_why
    // -----------------------------------------------------------
    //
    // Known bug LAB-40 (low): the recaptchav3 rule of the
    // registration and reminder forms
    // (RegisterController::store and forgotpassword) has no
    // message in lang/en/validation.php, so a refusal shows
    // its raw key, "validation.recaptchav3". And the forms
    // fetch their reCAPTCHA token once, when the page loads
    // (RecaptchaV3::field), while a token is good for two
    // minutes only: whoever takes longer to fill a form is
    // refused with that key, and on /register types both
    // passwords again. Fix: give the rule a message, and fetch
    // the token when the form is sent.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_40_a_refused_recaptcha_says_why(): void
    {
        $this->fakeRecaptcha('register', 0.1);

        $this->from('/register')->post('/register', [
            'username'              => 'Jonas',
            'email'                 => 'jonas@players.test',
            'password'              => 'long-enough-1',
            'password_confirmation' => 'long-enough-1',
            'g-recaptcha-response'  => self::RECAPTCHA_TOKEN,
        ]);
        $message = session('errors')?->first('g-recaptcha-response');

        $this->assertNotEmpty($message, 'the form was not refused');
        $this->assertDefectStillOpen('LAB-40', function () use ($message) {
            $this->assertNotSame('validation.recaptchav3', $message);
        });
    }
}
