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
//  that uploads are served from /upload and no PHP there
//  ever runs, that a forged client address is ignored, that
//  no answer names the server's versions, that PHP takes no
//  arguments from a query string and prints no diagnostics
//  into a page, that no PHP in the challenge-file folder ever
//  runs while its files are still handed out, that no page
//  among them opens as one of the lab's, and that CSRF is
//  enforced outside PHPUnit.
//
//  Used by:
//    - PHPUnit — the Http suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Http;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;








// -----------------------------------------------------------
// ApacheTest
// -----------------------------------------------------------
//
// Apache runs with the production environment run.sh gives
// it — none of phpunit.xml's testing values — and its own
// requests write to the database outside the tests'
// transactions, so the tests here only read, and the files
// they put into the upload and challenge-file folders are
// removed after each test.
//
//   fetch — one request, redirects not followed
//   plantUpload — a file in the upload folder
//   plantDownload — a file in the challenge-file folder
//
// Used by:
//   - PHPUnit — the Http suite (runTests.sh)
// -----------------------------------------------------------

final class ApacheTest extends TestCase
{

    // The address the vhost listens on, from ports.conf
    private const SERVER = 'http://127.0.0.1:8080';

    // The files the test put into the upload and
    // challenge-file folders
    private array $planted = [];

    // The folders it made for them there
    private array $plantedFolders = [];






    // -----------------------------------------------------------
    // tearDown
    // -----------------------------------------------------------
    //
    // Removes the files the test planted, then the folders it
    // made for them.
    //
    // Used by:
    //   - PHPUnit, after every test
    // -----------------------------------------------------------

    protected function tearDown(): void
    {
        foreach ($this->planted as $file) {
            @unlink($file);
        }
        foreach (array_reverse($this->plantedFolders) as $folder) {
            @rmdir($folder);
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
    // plantDownload
    // -----------------------------------------------------------
    //
    // Writes a file into the challenge-file folder — what the
    // live container mounts from _DATA/apache/downloads at
    // public/downloads, and runTests.sh gives a tmpfs — the
    // way an admin copying a challenge's files there would,
    // with the subfolder its name holds, and notes both for
    // removal.
    //
    // Used by:
    //   - test_php_in_the_challenge_file_folder_never_runs,
    //     test_challenge_files_are_served_as_files,
    //     test_a_challenge_page_is_handed_out_never_opened
    // -----------------------------------------------------------

    private function plantDownload(string $name, string $content): void
    {
        $path = public_path("downloads/{$name}");
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path));
            $this->plantedFolders[] = dirname($path);
        }
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
        $this->assertStringContainsString('data-recaptcha-action="forgotpass"', $this->fetch('GET', '/forgot-password')->body());
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
        foreach (['/.env', '/composer.json', '/storage/logs/laravel.log', '/app/Models/User.php'] as $path) {
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
    // test_php_in_the_upload_folder_never_runs
    // -----------------------------------------------------------
    //
    // A script that lands in the upload folder — an admin's
    // manual copy into _DATA/apache/public, say — is never run
    // or handed out: .php, .phtml and .phar files under
    // /upload answer 403. The lockdown in 000-default.conf, the
    // PHP engine off and script extensions denied, covers the
    // folder's real path and public/upload alike, as Apache
    // matches Directory sections against the path it walks,
    // never a symlink's target.
    //
    // Used by:
    //   - PHPUnit — the Http suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_php_in_the_upload_folder_never_runs(): void
    {
        $script = '<?php echo "EXECUTED-" . (6 * 7);';

        foreach (['shell.php', 'shell.phtml', 'shell.phar'] as $name) {
            $this->plantUpload($name, $script);
            $response = $this->fetch('GET', "/upload/{$name}");

            $this->assertSame(403, $response->status(), "{$name} was answered {$response->status()}");
            $this->assertStringNotContainsString('EXECUTED-42', $response->body(), "{$name} was executed");
        }
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
        $marker = 'forged-address-check-' . bin2hex(random_bytes(4));
        $this->fetch('GET', "/robots.txt?{$marker}", ['X-Forwarded-For' => '203.0.113.66']);

        // Apache writes the line once the answer is sent, so it
        // may land a moment after the answer does
        $lines = [];
        for ($try = 0; $try < 50 && ! $lines; $try++) {
            usleep($try ? 100000 : 0);
            $lines = preg_grep("/{$marker}/", file('/var/www/logs/access.log') ?: []);
        }
        $this->assertNotEmpty($lines);
        $this->assertStringStartsWith('127.0.0.1 ', end($lines));
        $this->assertStringNotContainsString('203.0.113.66', end($lines));
    }






    // -----------------------------------------------------------
    // test_responses_name_no_versions
    // -----------------------------------------------------------
    //
    // No answer names the PHP or Apache version: no
    // X-Powered-By header, a bare "Apache" for Server, and no
    // signature under Apache's own error pages, such as the 403
    // of a script in the upload folder.
    //
    // Used by:
    //   - PHPUnit — the Http suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_responses_name_no_versions(): void
    {
        $this->plantUpload('shell.php', '<?php echo 1;');

        foreach (['/', '/robots.txt', '/upload/shell.php'] as $path) {
            $response = $this->fetch('GET', $path);
            $this->assertSame('', $response->header('X-Powered-By'), $path);
            $this->assertSame('Apache', $response->header('Server'), $path);
            $this->assertDoesNotMatchRegularExpression('#Apache/\d|PHP/\d#', $response->body(), $path);
        }
    }






    // -----------------------------------------------------------
    // test_php_takes_no_arguments_from_a_query_string
    // -----------------------------------------------------------
    //
    // The image's PHP settings, which Apache's PHP reads as
    // well, turn register_argc_argv off, so a web request's
    // query string never becomes $_SERVER['argv'], from which
    // Laravel before 10.48.23 took its environment
    // (CVE-2024-52301); Laravel 13 no longer does, and the
    // setting stays as a second guard. The command line keeps
    // its arguments whatever the setting, so the settings
    // files are read here instead.
    //
    // Used by:
    //   - PHPUnit — the Http suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_php_takes_no_arguments_from_a_query_string(): void
    {
        $settings = [];
        foreach (array_filter(array_map('trim', explode(',', (string) php_ini_scanned_files()))) as $file) {
            $settings += parse_ini_file($file);
        }

        $this->assertArrayHasKey('register_argc_argv', $settings);
        $this->assertSame('', $settings['register_argc_argv']);
    }






    // -----------------------------------------------------------
    // test_a_path_above_the_document_root_is_refused
    // -----------------------------------------------------------
    //
    // A request whose path climbs above the document root, sent
    // as written: curl, under the HTTP client, removes the dot
    // segments before sending unless told to keep the path as
    // it is. Apache refuses such a path itself with a 400, and
    // the environment file next to public/ never comes back.
    //
    // Used by:
    //   - PHPUnit — the Http suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_path_above_the_document_root_is_refused(): void
    {
        $response = Http::withOptions(['allow_redirects' => false, 'curl' => [CURLOPT_PATH_AS_IS => true]])
            ->timeout(20)
            ->get(self::SERVER . '/../.env');

        $this->assertSame(400, $response->status());
        $this->assertStringNotContainsString('APP_KEY', $response->body());
    }






    // -----------------------------------------------------------
    // test_php_keeps_a_vu_sign_on_as_long_as_a_lab_session
    // -----------------------------------------------------------
    //
    // SimpleSAMLphp keeps the VU login in a PHP session file,
    // and a sign-out reaches sso.vu.lt only while that file is
    // there. The image's PHP settings, which Apache's PHP
    // reads as well, keep a session file for 36 hours, a lab
    // session's length, where PHP's default deletes one 24
    // minutes after its last write, so a student's sign-out
    // after a long lab session still ends the single sign-on.
    //
    // Used by:
    //   - PHPUnit — the Http suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_php_keeps_a_vu_sign_on_as_long_as_a_lab_session(): void
    {
        $this->assertGreaterThanOrEqual(36 * 3600, (int) ini_get('session.gc_maxlifetime'));
    }






    // -----------------------------------------------------------
    // test_the_image_has_a_folder_for_simplesamlphps_sessions
    // -----------------------------------------------------------
    //
    // The image carries the folder docker-compose mounts
    // SimpleSAMLphp's sessions into from the host, owned by
    // the app's user, so that a redeploy keeps the VU logins a
    // sign-out needs.
    //
    // Used by:
    //   - PHPUnit — the Http suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_image_has_a_folder_for_simplesamlphps_sessions(): void
    {
        $this->assertDirectoryExists('/var/www/simplesamlphp-sessions');
        $this->assertSame(1000, fileowner('/var/www/simplesamlphp-sessions'));
    }






    // -----------------------------------------------------------
    // test_php_in_the_challenge_file_folder_never_runs
    // -----------------------------------------------------------
    //
    // A script among the challenge files — a web challenge's
    // source, copied into _DATA/apache/downloads, which the
    // live container mounts at public/downloads — is never run
    // as the app, whose environment holds the database
    // password and APP_KEY: .php, .phtml and .phar files under
    // /downloads answer 403, the PHP engine is off there, and
    // an .htaccess in the folder is not read, so none can
    // switch PHP back on. The .htaccess planted here would
    // have a text file run as PHP, which the denied script
    // names could not show: the file comes back as its text.
    //
    // Used by:
    //   - PHPUnit — the Http suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_php_in_the_challenge_file_folder_never_runs(): void
    {
        $script = '<?php echo "EXECUTED-" . (6 * 7);';
        foreach (['shell.php', 'shell.phtml', 'shell.phar'] as $name) {
            $this->plantDownload($name, $script);
        }
        $this->plantDownload('switched-on/.htaccess', "php_flag engine on\nAddHandler application/x-httpd-php .txt\n");
        $this->plantDownload('switched-on/shell.php', $script);
        $this->plantDownload('switched-on/shell.txt', $script);

        foreach (['shell.php', 'shell.phtml', 'shell.phar', 'switched-on/shell.php'] as $name) {
            $response = $this->fetch('GET', "/downloads/{$name}");

            $this->assertSame(403, $response->status(), "/downloads/{$name} was answered {$response->status()}");
            $this->assertStringNotContainsString('EXECUTED-42', $response->body(), "/downloads/{$name} was executed");
        }
        $text = $this->fetch('GET', '/downloads/switched-on/shell.txt');
        $this->assertSame(200, $text->status());
        $this->assertStringStartsWith('text/plain', $text->header('Content-Type'));
        $this->assertSame($script, $text->body(), 'The .htaccess under /downloads was read');
    }






    // -----------------------------------------------------------
    // test_challenge_files_are_served_as_files
    // -----------------------------------------------------------
    //
    // The lockdown of the challenge-file folder leaves its
    // files alone: a file there is handed out as it is, with
    // its type, and an address naming no file there still
    // reaches the app, whose 404 page answers it.
    //
    // Used by:
    //   - PHPUnit — the Http suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_challenge_files_are_served_as_files(): void
    {
        // An empty zip archive: its end record alone
        $zip = "PK\x05\x06" . str_repeat("\x00", 18);
        $this->plantDownload('task.zip', $zip);

        $served = $this->fetch('GET', '/downloads/task.zip');
        $missing = $this->fetch('GET', '/downloads/missing.zip');

        $this->assertSame(200, $served->status());
        $this->assertSame('application/zip', $served->header('Content-Type'));
        $this->assertSame($zip, $served->body());
        $this->assertSame(404, $missing->status());
        $this->assertStringContainsString('Page not found', $missing->body());
    }






    // -----------------------------------------------------------
    // test_a_challenge_page_is_handed_out_never_opened
    // -----------------------------------------------------------
    //
    // A page among the challenge files — HTML, or an SVG
    // image, which can carry scripts as well — goes out as a
    // file to save, application/octet-stream, so it never
    // opens as a page of the lab's own site, where its
    // scripts would run with a signed-in player's session.
    // Its bytes are unchanged, and a name in capitals or with
    // a further extension after the page's is caught too.
    //
    // Used by:
    //   - PHPUnit — the Http suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_challenge_page_is_handed_out_never_opened(): void
    {
        $page = '<!DOCTYPE html><title>Sign in</title><script>document.title = "ran"</script>';
        $image = '<svg xmlns="http://www.w3.org/2000/svg"><script>document.title = "ran"</script></svg>';
        $files = ['phish.html' => $page, 'PHISH.HTM' => $page, 'phish.html.bak' => $page, 'logo.svg' => $image];
        foreach ($files as $name => $content) {
            $this->plantDownload($name, $content);
        }

        foreach ($files as $name => $content) {
            $response = $this->fetch('GET', "/downloads/{$name}");

            $this->assertSame(200, $response->status(), $name);
            $this->assertSame('application/octet-stream', $response->header('Content-Type'), $name);
            $this->assertSame($content, $response->body(), $name);
        }
    }






    // -----------------------------------------------------------
    // test_php_prints_no_diagnostics_outside_laravel
    // -----------------------------------------------------------
    //
    // The image's PHP settings switch display_errors off.
    // Laravel does so for its own pages, but SimpleSAMLphp's
    // under /simplesaml are not Laravel's, and its error
    // handler leaves warnings to PHP, which without a php.ini
    // prints them — the server's file paths among them — into
    // the page; PHP still logs them to Apache's error log. The
    // settings files are read here, as for register_argc_argv,
    // the later file winning as in PHP.
    //
    // Used by:
    //   - PHPUnit — the Http suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_php_prints_no_diagnostics_outside_laravel(): void
    {
        $settings = [];
        foreach (array_filter(array_map('trim', explode(',', (string) php_ini_scanned_files()))) as $file) {
            $settings = array_merge($settings, parse_ini_file($file));
        }

        $this->assertArrayHasKey('display_errors', $settings);
        $this->assertContains($settings['display_errors'], ['', '0']);
    }
}
