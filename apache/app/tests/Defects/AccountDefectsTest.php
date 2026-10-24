<?php

// -----------------------------------------------------------
//  [*] AccountDefectsTest — open defects of the account flows
//
//  Each test states the behaviour the lab wants where the
//  login, sign-out, registration and password flows falls
//  short today, under a LAB-nn id; KnownDefects reports it
//  incomplete while the defect is open and fails it once a
//  fix lands. The banner of every test gives the severity,
//  the mechanism, naming its files and methods, and the
//  fix.
//
//  Used by:
//    - PHPUnit — the Defects suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Defects;

use App\Http\Controllers\Users\RegisterController;
use App\Mail\SendNewPass;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Support\KnownDefects;
use Tests\TestCase;








// -----------------------------------------------------------
// AccountDefectsTest
// -----------------------------------------------------------
//
// LAB-57, LAB-61, LAB-62 and LAB-70.
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
    // test_lab_57_a_sign_out_pressed_again_leaves_no_page_to_resume
    // -----------------------------------------------------------
    //
    // Known bug LAB-57 (low): the signout_retry page
    // (VerifyCsrfToken::handle) offers /user/logout again, but
    // that route sits behind auth:users. When the session
    // behind the page has ended — the very case the page is
    // for — its button's POST passes the token check and is
    // refused by the guard, and Laravel keeps the address the
    // refused POST came from, the retry page's own
    // /user/logout, as the page to resume.
    // Controller::intendedWithin accepts it, as it lies under
    // /user, so the next login in that browser, by the form or
    // the VU SSO, ends on GET /user/logout, a 405 error page.
    // Fix: leave user/logout out of the retry page — a local
    // account has nothing left to end once its session is gone
    // — and have intendedWithin pass over the sign-outs.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_57_a_sign_out_pressed_again_leaves_no_page_to_resume(): void
    {
        $this->app['env'] = 'production';
        $player = $this->makePlayer();
        $this->signIn($player);
        $this->startSession();
        $stale = csrf_token();
        Auth::guard('users')->logout();
        $this->app['session']->regenerateToken();

        // The page answering the refused sign-out may offer it again;
        // its button is pressed when it does
        $retry = $this->post('/user/logout', ['_token' => $stale]);
        if (preg_match('/name="_token" value="([^"]+)"/', $retry->getContent(), $token)) {
            $this->from('https://lab.knf.vu.lt/user/logout')->post('/user/logout', ['_token' => $token[1]]);
        }
        $login = $this->post('/login', ['email' => $player->email, 'password' => self::PASSWORD, '_token' => csrf_token()]);

        $this->assertDefectStillOpen('LAB-57', function () use ($login) {
            $this->assertSame(url('/user/dashboard'), $login->headers->get('Location'), 'the login goes on to ' . $login->headers->get('Location'));
        });
    }






    // -----------------------------------------------------------
    // test_lab_61_a_double_clicked_registration_ends_without_a_server_error
    // -----------------------------------------------------------
    //
    // Known bug LAB-61 (low): RegisterController::store checks
    // that the username and e-mail are free, then asks
    // reCAPTCHA, then inserts, with nothing to keep two
    // requests apart, and the form's script (custom.js), which
    // fetches the token as the form is sent, lets a second
    // press send a second POST while the first waits for its
    // token. When both pass the checks before either inserts,
    // the second insert hits username_UNIQUE and the page the
    // browser shows is a 500, although the account was created
    // and its link mailed; the reminder form mails two links
    // the same way, and only the later one works. Here the
    // second registration runs in full right after the first
    // one's last check. Fix: answer the duplicate key as the
    // taken username it is, and have the script ignore a
    // second press while the token is on its way.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_61_a_double_clicked_registration_ends_without_a_server_error(): void
    {
        Mail::fake();
        $this->fakeRecaptcha('register', 0.9);
        $form = [
            'username'              => 'Jonas',
            'email'                 => 'jonas@players.test',
            'password'              => 'long-enough-1',
            'password_confirmation' => 'long-enough-1',
            'g-recaptcha-response'  => self::RECAPTCHA_TOKEN,
        ];
        $interleaved = false;
        DB::listen(function (QueryExecuted $query) use ($form, &$interleaved) {
            if (!$interleaved && str_starts_with($query->sql, 'select count(*) as aggregate from `cyber_users` where `email` = ?')) {
                $interleaved = true;
                app(RegisterController::class)->store(Request::create('/register', 'POST', $form));
            }
        });

        $response = $this->from('/register')->post('/register', $form);

        $this->assertTrue($interleaved);
        $this->assertSame(1, User::where('username', 'Jonas')->count());
        $this->assertDefectStillOpen('LAB-61', function () use ($response) {
            $response->assertRedirect();
        });
    }






    // -----------------------------------------------------------
    // test_lab_62_the_session_cookie_travels_over_https_only
    // -----------------------------------------------------------
    //
    // Known bug LAB-62 (low): config/session.php takes the
    // session cookie's Secure flag from SESSION_SECURE_COOKIE,
    // which nothing sets, so the session and XSRF-TOKEN
    // cookies go without it although the site is served over
    // https only. A browser would send them on a plain http
    // request to the host; the HSTS header and
    // upgrade-insecure-requests of endpoint/Caddyfile narrow
    // that to a first visit before HSTS is known. Fix: default
    // the flag to on.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_62_the_session_cookie_travels_over_https_only(): void
    {
        $cookie = $this->get('/')->getCookie(config('session.cookie'), false);

        $this->assertNotNull($cookie);
        $this->assertDefectStillOpen('LAB-62', function () use ($cookie) {
            $this->assertTrue($cookie->isSecure());
        });
    }






    // -----------------------------------------------------------
    // test_lab_70_a_password_the_panel_mails_signs_in
    // -----------------------------------------------------------
    //
    // Known bug LAB-70 (low): Admins\UsersController::update
    // gives any account the password typed and mails it, but
    // the login form finds an account by its e-mail alone and
    // takes the first one, while a VU account is created with
    // the e-mail its student may already use for a local
    // account (UserAuthController::check_member does not
    // look). A password set on such a VU account — the newer
    // row, at the top of the newest-first list — never signs
    // in: the login reaches the local account and says the
    // credentials are wrong. Fix: refuse a password for a VU
    // account whose e-mail a local account also holds, or let
    // the login try every account with the e-mail.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_70_a_password_the_panel_mails_signs_in(): void
    {
        Mail::fake();
        $this->makePlayer(['email' => 'jonas@stud.vu.lt']);
        $vu = $this->makeSsoPlayer(['email' => 'jonas@stud.vu.lt', 'password' => '']);

        $this->signInAdmin($this->makeAdmin(true))->post('/control-panel/dialog/users/update', ['uid' => $vu->id, 'password' => 'new-pass-123', 'notif' => 1]);

        $mailed = Mail::sent(SendNewPass::class)->isNotEmpty();
        $signsIn = Auth::guard('users')->attempt(['email' => 'jonas@stud.vu.lt', 'password' => 'new-pass-123']);
        $this->assertDefectStillOpen('LAB-70', function () use ($mailed, $signsIn) {
            $this->assertTrue($signsIn || ! $mailed, 'the password mailed does not sign in');
        });
    }
}
