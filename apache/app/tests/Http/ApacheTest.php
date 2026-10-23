<?php

// -----------------------------------------------------------
//  [*] ApacheTest — the web server configuration that ships
//
//  Every other suite talks to Laravel directly; this one
//  sends real HTTP requests to the image's own Apache — the
//  vhost, ports and rewrite rules the Dockerfile installs,
//  which run.sh starts the way the live container runs it —
//  over the tests container's loopback. It pins what only
//  the web server decides: that public/ is the document root
//  and everything else stays out of reach, that the rewrite
//  rules hand pretty addresses to Laravel, that pages carry
//  no PHP diagnostics, that directories are never listed,
//  that uploads are served from /upload, that a forged
//  client address is ignored, and that CSRF is enforced
//  outside PHPUnit. The upload folder's PHP lockdown
//  does not hold today; that is LAB-18, pinned below.
//
//  Used by:
//    - PHPUnit — the Http suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Http;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Tests\Support\KnownDefects;
use Tests\TestCase;








// -----------------------------------------------------------
// ApacheTest
// -----------------------------------------------------------
//
// Apache runs with the production environment run.sh gives
// it — none of phpunit.xml's testing values — and its own
// requests write to the database outside the tests'
// transactions, so the tests here only read, and the files
// they put into the upload folder are removed after each
// test.
//
//   fetch — one request, redirects not followed
//   plantUpload — a file in the upload folder
//
// Used by:
//   - PHPUnit — the Http suite (runTests.sh)
// -----------------------------------------------------------

final class ApacheTest extends TestCase
{

    use KnownDefects;

    // The address the vhost listens on, from ports.conf
    private const SERVER = 'http://127.0.0.1:8080';

    // The files the test put into the upload folder
    private array $planted = [];






    // -----------------------------------------------------------
    // tearDown
    // -----------------------------------------------------------
    //
    // Removes the files the test planted.
    //
    // Used by:
    //   - PHPUnit, after every test
    // -----------------------------------------------------------

    protected function tearDown(): void
    {
        foreach ($this->planted as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }






    // -----------------------------------------------------------
    // fetch
    // -----------------------------------------------------------
    //
    // Sends one request to Apache with the headers given and
    // returns the answer as it comes — a redirect is not
    // followed, an error status does not throw.
    //
    // Used by:
    //   - the tests below
    // -----------------------------------------------------------

    private function fetch(string $method, string $path, array $headers = []): Response
    {
        return Http::withOptions(['allow_redirects' => false])
            ->withHeaders($headers)
            ->timeout(20)
            ->send($method, self::SERVER . $path);
    }






    // -----------------------------------------------------------
    // plantUpload
    // -----------------------------------------------------------
    //
    // Writes a file into the upload folder — what the live
    // container mounts from _DATA/apache/public — the way an
    // admin copying files there would, and notes it for
    // removal.
    //
    // Used by:
    //   - the upload tests below
    // -----------------------------------------------------------

    private function plantUpload(string $name, string $content): void
    {
        $path = storage_path("app/public/{$name}");
        file_put_contents($path, $content);
        $this->planted[] = $path;
    }






    // -----------------------------------------------------------
    // test_the_front_page_is_served_by_laravel
    // -----------------------------------------------------------
    //
    // The front page comes through the front controller,
    // rendered by the app with its database and Redis behind
    // it.
    //
    // Used by:
    //   - PHPUnit — the Http suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_front_page_is_served_by_laravel(): void
    {
        $response = $this->fetch('GET', '/');

        $this->assertSame(200, $response->status());
        $this->assertStringContainsString('Login with VU SSO', $response->body());
    }






    // -----------------------------------------------------------
    // test_pretty_addresses_reach_laravel
    // -----------------------------------------------------------
    //
    // An address that names no file is rewritten to the front
    // controller, which routes it.
    //
    // Used by:
    //   - PHPUnit — the Http suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_pretty_addresses_reach_laravel(): void
    {
        $this->assertStringContainsString('name="password_confirmation"', $this->fetch('GET', '/register')->body());
        $this->assertStringContainsString("{action: 'forgotpass'}", $this->fetch('GET', '/forgot-password')->body());
        $this->assertStringContainsString('Login with VU SSO', $this->fetch('GET', '/control-panel')->body());
    }






    // -----------------------------------------------------------
    // test_pages_carry_no_php_diagnostics
    // -----------------------------------------------------------
    //
    // No page shows a PHP notice, warning, deprecation or fatal
    // error, and the answers keep their headers — the session
    // cookie, a redirect's address — which a diagnostic
    // printed before them would cost. PHP 8.4 reports much of
    // the locked vendor code as deprecated before Laravel's
    // error handler takes over; the image's error_reporting
    // setting keeps that out of the pages (Dockerfile). The
    // 404 page has no session, so no cookie is asked of it.
    //
    // Used by:
    //   - PHPUnit — the Http suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_pages_carry_no_php_diagnostics(): void
    {
        $diagnostic = '#<b>(Deprecated|Warning|Notice|Fatal error)</b>:|^(Deprecated|Warning|Notice|Fatal error): .* on line \d+#m';

        foreach (['/', '/register', '/forgot-password', '/control-panel', '/no-such-page'] as $path) {
            $response = $this->fetch('GET', $path);
            $this->assertDoesNotMatchRegularExpression($diagnostic, $response->body(), $path);
            if ($path !== '/no-such-page') {
                $this->assertNotEmpty($response->header('Set-Cookie'), $path);
            }
        }

        $redirect = $this->fetch('GET', '/user/dashboard');

        $this->assertSame(302, $redirect->status());
        $this->assertNotEmpty($redirect->header('Location'));
        $this->assertDoesNotMatchRegularExpression($diagnostic, $redirect->body());
    }






    // -----------------------------------------------------------
    // test_a_trailing_slash_is_redirected_away
    // -----------------------------------------------------------
    //
    // An address with a trailing slash that is no directory is
    // redirected permanently to the address without it.
    //
    // Used by:
    //   - PHPUnit — the Http suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_trailing_slash_is_redirected_away(): void
    {
        $response = $this->fetch('GET', '/register/');

        $this->assertSame(301, $response->status());
        $this->assertSame(self::SERVER . '/register', $response->header('Location'));
    }






    // -----------------------------------------------------------
    // test_static_files_are_served_directly
    // -----------------------------------------------------------
    //
    // Files in public/ are served as files, with their type.
    //
    // Used by:
    //   - PHPUnit — the Http suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_static_files_are_served_directly(): void
    {
        $css = $this->fetch('GET', '/assets/css/app.min.css');
        $robots = $this->fetch('GET', '/robots.txt');

        $this->assertSame(200, $css->status());
        $this->assertStringStartsWith('text/css', $css->header('Content-Type'));
        $this->assertSame(200, $robots->status());
        $this->assertStringStartsWith('text/plain', $robots->header('Content-Type'));
    }






    // -----------------------------------------------------------
    // test_directories_are_never_listed
    // -----------------------------------------------------------
    //
    // A directory without an index answers 403 instead of a
    // listing — the asset folders and the upload folder alike.
    //
    // Used by:
    //   - PHPUnit — the Http suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_directories_are_never_listed(): void
    {
        $this->plantUpload('listed.png', 'not listed');

        foreach (['/assets/', '/assets/css/', '/upload/'] as $directory) {
            $response = $this->fetch('GET', $directory);
            $this->assertSame(403, $response->status(), $directory);
            $this->assertStringNotContainsString('Index of', $response->body(), $directory);
        }
    }






    // -----------------------------------------------------------
    // test_nothing_outside_public_can_be_fetched
    // -----------------------------------------------------------
    //
    // The document root is public/: the environment file, the
    // composer files, the logs and the code next to it are
    // unknown addresses to the outside, answered by the app's
    // 404 page.
    //
    // Used by:
    //   - PHPUnit — the Http suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_nothing_outside_public_can_be_fetched(): void
    {
        foreach (['/.env', '/composer.json', '/storage/logs/laravel.log', '/app/Models/User.php', '/../.env'] as $path) {
            $response = $this->fetch('GET', $path);
            $this->assertSame(404, $response->status(), $path);
            $this->assertStringContainsString('Page not found', $response->body(), $path);
        }
    }






    // -----------------------------------------------------------
    // test_uploaded_images_are_served_from_upload
    // -----------------------------------------------------------
    //
    // A file in the upload folder is served under /upload,
    // where the challenge pages point partner logos.
    //
    // Used by:
    //   - PHPUnit — the Http suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_uploaded_images_are_served_from_upload(): void
    {
        $this->plantUpload('served.gif', 'GIF89a' . "\x01\x00\x01\x00\x00\x00\x00;");

        $response = $this->fetch('GET', '/upload/served.gif');

        $this->assertSame(200, $response->status());
        $this->assertSame('image/gif', $response->header('Content-Type'));
    }






    // -----------------------------------------------------------
    // test_lab_18_php_in_the_upload_folder_never_runs
    // -----------------------------------------------------------
    //
    // Known bug LAB-18 (high): a PHP file in the upload folder
    // is executed when requested under /upload, and .phtml
    // and .phar files are handed out as source. The lockdown
    // in 000-default.conf — the PHP engine off, script
    // extensions denied — is written for
    // /var/www/html/storage/app/public, but the files are
    // reached through the public/upload symlink, and Apache
    // matches Directory sections against the path it walked,
    // never the symlink's target, so the section never
    // applies. Uploads through the app are safe — they are
    // stored under extensions taken from their content — but
    // anything else that lands in _DATA/apache/public, an
    // admin's manual copy included, runs as code. Fix: apply
    // the same lockdown to /var/www/html/public/upload as
    // well.
    //
    // Used by:
    //   - PHPUnit — the Http suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_18_php_in_the_upload_folder_never_runs(): void
    {
        $script = '<?php echo "EXECUTED-" . (6 * 7);';
        $answers = [];
        foreach (['shell.php', 'shell.phtml', 'shell.phar'] as $name) {
            $this->plantUpload($name, $script);
            $answers[$name] = $this->fetch('GET', "/upload/{$name}");
        }

        $this->assertDefectStillOpen('LAB-18', function () use ($answers) {
            foreach ($answers as $name => $response) {
                $this->assertSame(403, $response->status(), "{$name} was answered {$response->status()}");
                $this->assertStringNotContainsString('EXECUTED-42', $response->body(), "{$name} was executed");
            }
        });
    }






    // -----------------------------------------------------------
    // test_a_form_without_its_token_is_refused
    // -----------------------------------------------------------
    //
    // Outside PHPUnit nothing switches the CSRF check off: a
    // login posted without a token ends on the 419 page.
    //
    // Used by:
    //   - PHPUnit — the Http suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_form_without_its_token_is_refused(): void
    {
        $response = $this->fetch('POST', '/login');

        $this->assertSame(419, $response->status());
        $this->assertStringContainsString('Error 419', $response->body());
    }






    // -----------------------------------------------------------
    // test_a_forged_client_address_is_ignored
    // -----------------------------------------------------------
    //
    // Apache takes the client address from X-Forwarded-For
    // only when the request comes from the stack's own
    // network (RemoteIPInternalProxy 172.0.0.0/8); from
    // anywhere else the header is ignored, and the access log
    // keeps the real address.
    //
    // Used by:
    //   - PHPUnit — the Http suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_forged_client_address_is_ignored(): void
    {
        $this->fetch('GET', '/robots.txt?forged-address-check', ['X-Forwarded-For' => '203.0.113.66']);

        $lines = preg_grep('/forged-address-check/', file('/var/www/logs/access.log') ?: []);
        $this->assertNotEmpty($lines);
        $this->assertStringStartsWith('127.0.0.1 ', end($lines));
        $this->assertStringNotContainsString('203.0.113.66', end($lines));
    }
}
