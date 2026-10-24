<?php

// -----------------------------------------------------------
//  [*] AccountDefectsTest — open defects of the account flows
//
//  Each test states the behaviour the lab wants where the
//  login, registration, password reset and VU provisioning
//  fall short today, under a LAB-nn id; KnownDefects reports
//  it incomplete while the defect is open and fails it once a
//  fix lands. The banner of every test gives the severity,
//  the mechanism, naming its files and methods, and the
//  fix. The tests that replace the SimpleSAMLphp client are
//  SsoDefectsTest's.
//
//  Used by:
//    - PHPUnit — the Defects suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Defects;

use App\Http\Controllers\Users\UserAuthController;
use App\Mail\SendRemindLink;
use App\Mail\UserVerifyMail;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Support\KnownDefects;
use Tests\TestCase;








// -----------------------------------------------------------
// AccountDefectsTest
// -----------------------------------------------------------
//
// LAB-38, LAB-40, LAB-43, LAB-45 and LAB-52.
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
    // test_lab_38_a_reset_lets_other_accounts_be_written_while_it_mails
    // -----------------------------------------------------------
    //
    // Known bug LAB-38 (medium):
    // RegisterController::reset_pass claims the link with an
    // UPDATE that finds the account by remember_token, a
    // column without an index, so MySQL scans and locks every
    // row of cyber_users and the gaps between them; and it
    // holds those locks through the mail it sends next, as
    // both run in one transaction. Until the mail server
    // answers, no account can be written: logins, right
    // answers, registrations and first VU logins wait, and
    // fail with a lock timeout should the mail take longer
    // than 50 seconds. Here a second connection tries to add
    // an account while the mail is sent, and gives up after a
    // second. Fix: read the account's id first, without
    // locking, and claim the link with an UPDATE by that id,
    // so only the account's own row is locked while the mail
    // goes out.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_38_a_reset_lets_other_accounts_be_written_while_it_mails(): void
    {
        $player = $this->makePlayer();
        $token = Str::random(50) . '-' . now()->getTimestamp();
        $player->update(['remember_token' => hash('sha256', $token)]);
        config(['database.connections.probe' => config('database.connections.mysql')]);
        $blocked = null;
        Event::listen(MessageSending::class, function () use (&$blocked) {
            $probe = DB::connection('probe');
            $probe->statement('SET SESSION innodb_lock_wait_timeout = 1');
            $probe->beginTransaction();
            try {
                $probe->table('cyber_users')->insert(['username' => 'probe_' . Str::random(10), 'email' => 'probe@players.test', 'password' => 'x']);
                $blocked = false;
            } catch (QueryException $e) {
                $blocked = true;
            } finally {
                $probe->rollBack();
            }
        });

        $this->post("/reset/{$token}")->assertRedirect('/');
        DB::purge('probe');

        $this->assertNotNull($blocked, 'the reset mailed nothing');
        $this->assertDefectStillOpen('LAB-38', function () use ($blocked) {
            $this->assertFalse($blocked, 'no account could be written while the reset mailed its password');
        });
    }






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






    // -----------------------------------------------------------
    // test_lab_43_an_account_switched_off_gets_no_verification_link
    // -----------------------------------------------------------
    //
    // Known bug LAB-43 (low): UserAuthController::login reads
    // an inactive account with a verify_token as one never
    // verified and mails it a fresh link, whose opening
    // switches the account on. Before 2026-10-23 the control
    // panel's status switch left verify_token as it was, so an
    // account signed up but never verified, switched on by an
    // admin and later switched off again still has its token:
    // its owner now switches it back on with the password
    // alone. Fix: mail a fresh link only to an account that
    // has never signed in (last_login empty), or clear
    // verify_token on those rows once.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_43_an_account_switched_off_gets_no_verification_link(): void
    {
        Mail::fake();
        $player = $this->makePlayer([
            'status'       => 0,
            'verify_token' => hash('sha256', 'left-by-the-old-switch'),
            'last_login'   => '2026-09-01 10:00:00',
        ]);

        $this->post('/login', ['email' => $player->email, 'password' => self::PASSWORD])->assertRedirect('/');

        $this->assertDefectStillOpen('LAB-43', function () {
            Mail::assertNotSent(UserVerifyMail::class);
            $this->assertErrorFlashed('Your account is not active');
        });
    }






    // -----------------------------------------------------------
    // test_lab_45_the_reminder_goes_to_the_stored_address
    // -----------------------------------------------------------
    //
    // Known bug LAB-45 (low):
    // RegisterController::forgotpassword finds the account
    // with the typed address, compared under the column's
    // utf8mb4_unicode_ci collation, which ignores case and
    // accents, but mails the link to the address as typed. The
    // owner of a look-alike domain, gmáil.com for gmail.com,
    // receives the reminder of jonas@gmail.com and can reset
    // that player's password at will; the new password itself
    // still goes to the stored address, so it is a nuisance
    // rather than a takeover. Fix: mail the account's stored
    // address.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_45_the_reminder_goes_to_the_stored_address(): void
    {
        Mail::fake();
        $this->fakeRecaptcha('forgotpass', 0.9);
        $this->makePlayer(['email' => 'jonas@gmail.com']);

        $this->from('/forgot-password')->post('/forgot-password', [
            'email'                => 'jonas@gmáil.com',
            'g-recaptcha-response' => self::RECAPTCHA_TOKEN,
        ]);

        $this->assertDefectStillOpen('LAB-45', function () {
            Mail::assertNotSent(SendRemindLink::class, fn (SendRemindLink $mail) => ! $mail->hasTo('jonas@gmail.com'));
        });
    }






    // -----------------------------------------------------------
    // test_lab_52_two_first_vu_logins_at_once_make_one_account
    // -----------------------------------------------------------
    //
    // Known bug LAB-52 (low): UserAuthController::check_member
    // counts the accounts with the VU user id, then looks for
    // a free username, then creates the account, with nothing
    // to keep two requests apart, and cyber_users.vu_id has no
    // unique key. A student's first VU login reaching /cp
    // twice at once — two tabs, or a double click on the login
    // button — creates a second account, named with a _2, or,
    // when both pick the same username, ends one tab in a
    // server error. Here the second login runs in full right
    // after the first one's count. Fix: give vu_id a unique
    // key and take the existing account when the insert finds
    // the id taken, or serialize the provisioning with a lock.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_52_two_first_vu_logins_at_once_make_one_account(): void
    {
        $attributes = [
            'urn:oid:0.9.2342.19200300.100.1.1' => ['s7654321'],
            'urn:oid:0.9.2342.19200300.100.1.3' => ['ona@stud.vu.lt'],
            'urn:oid:2.5.4.42'                  => ['Ona'],
            'urn:oid:2.5.4.4'                   => ['Onaitė'],
        ];
        $interleaved = false;
        DB::listen(function (QueryExecuted $query) use ($attributes, &$interleaved) {
            if (!$interleaved && str_starts_with($query->sql, 'select count(*) as aggregate from `cyber_users` where (`vu_id` = ?)')) {
                $interleaved = true;
                UserAuthController::check_member($attributes);
            }
        });

        $id = UserAuthController::check_member($attributes);

        $this->assertTrue($interleaved);
        $this->assertDefectStillOpen('LAB-52', function () use ($id) {
            $this->assertSame(1, User::where('vu_id', 's7654321')->count());
            $this->assertSame(User::where('vu_id', 's7654321')->value('id'), $id);
        });
    }
}
