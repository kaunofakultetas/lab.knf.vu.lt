<?php

// -----------------------------------------------------------
//  [*] PasswordResetDefectsTest — open defects in the reminder
//
//  Each test states the behaviour the lab wants where the
//  forgotten-password reminder falls short today, under a
//  LAB-nn id; KnownDefects reports it incomplete while the
//  defect is open and fails it once a fix lands. Like
//  PasswordResetTest, the tests take every link from the mail
//  it was sent in.
//
//  Used by:
//    - PHPUnit — the Defects suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Defects;

use App\Mail\SendNewPass;
use App\Mail\SendRemindLink;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\Support\KnownDefects;
use Tests\TestCase;








// -----------------------------------------------------------
// PasswordResetDefectsTest
// -----------------------------------------------------------
//
// LAB-25 and LAB-27. Mail is faked in every test.
//
//   mailedLink — the token of a freshly mailed reminder
//
// Used by:
//   - PHPUnit — the Defects suite (runTests.sh)
// -----------------------------------------------------------

final class PasswordResetDefectsTest extends TestCase
{

    use KnownDefects;






    // -----------------------------------------------------------
    // setUp
    // -----------------------------------------------------------
    //
    // Fakes the mailer.
    //
    // Used by:
    //   - PHPUnit, before every test
    // -----------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }






    // -----------------------------------------------------------
    // mailedLink
    // -----------------------------------------------------------
    //
    // Asks for a reminder for the player, with a passing
    // reCAPTCHA verdict, and returns the token of the link
    // the reminder mail carries.
    //
    // Used by:
    //   - the tests below
    // -----------------------------------------------------------

    private function mailedLink(User $player): string
    {
        $this->fakeRecaptcha('forgotpass', 0.9);
        $this->post('/forgot-password', ['email' => $player->email, 'g-recaptcha-response' => self::RECAPTCHA_TOKEN]);
        return Mail::sent(SendRemindLink::class, fn (SendRemindLink $mail) => $mail->hasTo($player->email))->last()->data['uid'];
    }






    // -----------------------------------------------------------
    // test_lab_25_a_reminder_link_expires
    // -----------------------------------------------------------
    //
    // Known bug LAB-25 (medium): a reminder link never
    // expires — one found in an old mailbox, a forwarded mail
    // or a log works months later — as nothing records when
    // it was mailed: the token sits in remember_token without
    // a time. Fix: record when the reminder was sent and
    // refuse links older than an hour, as Laravel's own
    // password broker does. The test moves Laravel's clock two
    // days on, so the fix must read the time through it.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_25_a_reminder_link_expires(): void
    {
        $link = $this->mailedLink($this->makePlayer());
        $this->travel(2)->days();

        $response = $this->get("/reset/{$link}");

        $this->assertDefectStillOpen('LAB-25', function () use ($response) {
            $response->assertNotFound();
            Mail::assertNotSent(SendNewPass::class);
        });
    }






    // -----------------------------------------------------------
    // test_lab_27_opening_a_link_changes_no_password
    // -----------------------------------------------------------
    //
    // Known bug LAB-27 (low): merely opening a reminder link
    // sets and mails a new password — GET /reset/{token}
    // (RegisterController::reset_pass) does the reset. Mail
    // filters and link scanners open the links in incoming
    // mail, so a reminder can be used up, and the password
    // changed, before the player clicks anything. Fix: let the
    // link open a page whose button posts the reset; the home
    // tests follow the link by GET today and move to the
    // button with the fix.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_27_opening_a_link_changes_no_password(): void
    {
        $player = $this->makePlayer();
        $link = $this->mailedLink($player);
        $hash = $player->refresh()->password;

        $this->get("/reset/{$link}");

        $this->assertDefectStillOpen('LAB-27', function () use ($player, $hash) {
            $this->assertSame($hash, $player->refresh()->password, 'opening the link changed the password');
            Mail::assertNotSent(SendNewPass::class);
        });
    }
}
