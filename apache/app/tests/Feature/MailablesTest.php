<?php

// -----------------------------------------------------------
//  [*] MailablesTest — what the four mails say
//
//  The registration verification link, the password reminder
//  link, the new password, and the admin-written template
//  mail. The links in the first two are built from APP_URL,
//  so a wrong APP_URL in production breaks them silently;
//  these tests render each mail the way it is sent and pin
//  its subject, its links and what it must escape.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature;

use App\Mail\SendMail;
use App\Mail\SendNewPass;
use App\Mail\SendRemindLink;
use App\Mail\UserVerifyMail;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;








// -----------------------------------------------------------
// MailablesTest
// -----------------------------------------------------------
//
// The mails are built and rendered as the mailer would —
// their subjects are only set while building — but never
// sent; APP_URL is the live one, https://lab.knf.vu.lt, as
// tests/container/run.sh exports it.
//
// Used by:
//   - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------

final class MailablesTest extends TestCase
{






    // -----------------------------------------------------------
    // test_the_verification_mail_links_the_verify_route
    // -----------------------------------------------------------
    //
    // The registration mail thanks the player and links the
    // verification route with the account's token.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_verification_mail_links_the_verify_route(): void
    {
        $mail = (new UserVerifyMail(['uid' => 'Tok3nOfFiftyChars']))->build();

        $mail->assertHasSubject('Verify your VU KNF Lab account');
        $mail->assertSeeInHtml('Thank you for registration');
        $mail->assertSeeInHtml('href="https://lab.knf.vu.lt/verify/Tok3nOfFiftyChars"', false);
    }






    // -----------------------------------------------------------
    // test_the_reminder_mail_links_the_reset_route
    // -----------------------------------------------------------
    //
    // The reminder mail links the reset route with the token.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_reminder_mail_links_the_reset_route(): void
    {
        $mail = (new SendRemindLink(['uid' => 'Tok3nOfFiftyChars']))->build();

        $mail->assertHasSubject('Reset Password - VU KNF Lab');
        $mail->assertSeeInHtml('href="https://lab.knf.vu.lt/reset/Tok3nOfFiftyChars"', false);
    }






    // -----------------------------------------------------------
    // test_the_new_password_mail_carries_the_login
    // -----------------------------------------------------------
    //
    // The new-password mail names the login email, the
    // password and the address of the lab — the password
    // escaped, so that one with markup characters arrives as
    // typed.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_new_password_mail_carries_the_login(): void
    {
        $mail = (new SendNewPass(['email' => 'ona@players.test', 'new_pass' => 'a<b>&c']))->build();

        $mail->assertHasSubject('New Password - VU KNF Lab');
        $mail->assertSeeInHtml('Login email: ona@players.test');
        $mail->assertSeeInHtml('<b>a&lt;b&gt;&amp;c</b>', false);
        $mail->assertSeeInHtml('href="https://lab.knf.vu.lt"', false);
    }






    // -----------------------------------------------------------
    // test_a_template_mail_is_sent_as_written
    // -----------------------------------------------------------
    //
    // A template mail goes out with the template's subject and
    // exactly its HTML, with no layout added around it.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_template_mail_is_sent_as_written(): void
    {
        $mail = new SendMail('The spring challenges are open', '<p>New <b>challenges</b>.</p>');

        $mail->assertHasSubject('The spring challenges are open');
        $this->assertSame('<p>New <b>challenges</b>.</p>', $mail->render());
    }






    // -----------------------------------------------------------
    // test_the_mailer_never_sends_without_tls
    // -----------------------------------------------------------
    //
    // The SMTP mailer requires TLS: on port 587 it refuses to
    // send when the server offers no STARTTLS, so an attacker
    // who strips the offer reads neither the Mailjet login nor
    // any mail.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_mailer_never_sends_without_tls(): void
    {
        $transport = Mail::mailer('smtp')->getSymfonyTransport();

        $this->assertTrue($transport->isTlsRequired());
    }






    // -----------------------------------------------------------
    // test_only_the_environment_lifts_the_tls_requirement
    // -----------------------------------------------------------
    //
    // MAIL_REQUIRE_TLS set to false lifts the requirement, for
    // a relay that offers no TLS at all, as the README says;
    // without it TLS stays required. The mail settings are
    // read afresh from config/mail.php for each case.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_only_the_environment_lifts_the_tls_requirement(): void
    {
        $_SERVER['MAIL_REQUIRE_TLS'] = 'false';
        try {
            $lifted = (require config_path('mail.php'))['mailers']['smtp']['require_tls'];
        } finally {
            unset($_SERVER['MAIL_REQUIRE_TLS']);
        }

        $this->assertFalse($lifted);
        $this->assertTrue((require config_path('mail.php'))['mailers']['smtp']['require_tls']);
    }






    // -----------------------------------------------------------
    // test_the_mailer_speaks_tls_from_its_first_byte
    // -----------------------------------------------------------
    //
    // The lab's mail goes to Mailjet on port 465, the .env's
    // MAIL_PORT, where the connection is TLS from its first
    // byte: on 587 the mailer would wait for STARTTLS, and
    // someone on the way who makes the server's answer to
    // EHLO fail has Symfony fall back to HELO and send the
    // mail in clear, require_tls or not. The exit proxy passes
    // Mailjet on 465 alone, so a change of port fails every
    // mail too.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_mailer_speaks_tls_from_its_first_byte(): void
    {
        $transport = Mail::mailer('smtp')->getSymfonyTransport();

        $this->assertSame('465', (string) config('mail.mailers.smtp.port'));
        $this->assertTrue($transport->getStream()->isTLS());
    }
}
