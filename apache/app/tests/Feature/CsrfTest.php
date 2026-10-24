<?php

// -----------------------------------------------------------
//  [*] CsrfTest — every form needs its session's token
//
//  Laravel's CSRF check stands in front of every POST
//  route: a form must carry the token of the session it was
//  served in, as a field or as the X-CSRF-TOKEN header
//  scripts send — or come, as the browser's Sec-Fetch-Site
//  header says, from a page of the site itself — or the
//  request ends on the 419 page before anything else runs.
//  Laravel skips the check under PHPUnit, so these tests
//  switch the application's environment away from "testing"
//  first — the one thing the check looks at.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature;

use App\Models\Feedback;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;








// -----------------------------------------------------------
// CsrfTest
// -----------------------------------------------------------
//
//   postRoutes — the POST routes of a part of the site
//
// Used by:
//   - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------

final class CsrfTest extends TestCase
{






    // -----------------------------------------------------------
    // setUp
    // -----------------------------------------------------------
    //
    // Turns the CSRF check back on by leaving the "testing"
    // environment.
    //
    // Used by:
    //   - PHPUnit, before every test
    // -----------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['env'] = 'production';
    }






    // -----------------------------------------------------------
    // postRoutes
    // -----------------------------------------------------------
    //
    // The addresses of the POST routes whose uri passes the
    // filter, parameters set to 1, the dev-only _ignition
    // routes left out.
    //
    // Used by:
    //   - test_every_form_without_its_token_ends_on_the_419_page
    //     (below)
    // -----------------------------------------------------------

    private function postRoutes(callable $filter): array
    {
        $addresses = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $uri = $route->uri();
            if (in_array('POST', $route->methods(), true) && !Str::startsWith($uri, '_ignition') && $filter($uri)) {
                $addresses[] = '/' . preg_replace('/\{[^}]+\}/', '1', $uri);
            }
        }
        return $addresses;
    }






    // -----------------------------------------------------------
    // test_every_form_without_its_token_ends_on_the_419_page
    // -----------------------------------------------------------
    //
    // Every POST route — the guest forms as a guest, the
    // player forms as a player, the control-panel forms as a
    // main admin, so that no other guard answers first — is
    // refused with 419 when the token is missing.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_every_form_without_its_token_ends_on_the_419_page(): void
    {
        $guest = $this->postRoutes(fn (string $uri) => !Str::startsWith($uri, ['user/', 'control-panel/']));
        $player = $this->postRoutes(fn (string $uri) => Str::startsWith($uri, 'user/'));
        $admin = $this->postRoutes(fn (string $uri) => Str::startsWith($uri, 'control-panel/'));
        $this->assertEqualsCanonicalizing(['/login', '/register', '/forgot-password', '/reset/1'], $guest);

        foreach ($guest as $address) {
            $this->post($address)->assertStatus(419);
        }
        $this->signIn($this->makePlayer());
        foreach ($player as $address) {
            $this->post($address)->assertStatus(419);
        }
        $this->signInAdmin($this->makeAdmin(true));
        foreach ($admin as $address) {
            $this->post($address)->assertStatus(419);
        }

        $this->assertGreaterThan(20, count($admin));
    }






    // -----------------------------------------------------------
    // test_the_419_page_says_the_session_timed_out
    // -----------------------------------------------------------
    //
    // The refusal renders the lab's own error page, and the
    // form's work is not done.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_419_page_says_the_session_timed_out(): void
    {
        $player = $this->makePlayer();

        $response = $this->signIn($player)->post('/user/feedback', ['feedback' => 'Forged.']);

        $response->assertStatus(419);
        $response->assertSee('Error 419');
        $response->assertSee('Authentication Timeout');
        $this->assertSame(0, Feedback::where('user_id', $player->id)->count());
    }






    // -----------------------------------------------------------
    // test_a_form_with_its_token_passes
    // -----------------------------------------------------------
    //
    // The session's token as a form field, or as the header
    // scripts send, lets the request through to the
    // controller. A login hands out a new token, so the page
    // after it carries another one than the login form did.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_form_with_its_token_passes(): void
    {
        $player = $this->makePlayer();
        $this->startSession();
        $token = csrf_token();

        $this->post('/login', ['_token' => $token, 'email' => $player->email, 'password' => self::PASSWORD])
            ->assertRedirect('/user/dashboard');
        $this->assertNotSame($token, csrf_token());
        $this->withHeader('X-CSRF-TOKEN', csrf_token())
            ->post('/user/feedback', ['feedback' => 'Sent by a script.'])
            ->assertRedirect();

        $this->assertSame(1, Feedback::where('user_id', $player->id)->count());
    }






    // -----------------------------------------------------------
    // test_a_token_from_another_session_is_refused
    // -----------------------------------------------------------
    //
    // Once the session's token changes, the old one is worth
    // nothing.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_token_from_another_session_is_refused(): void
    {
        $player = $this->makePlayer();
        $this->startSession();
        $stale = csrf_token();
        $this->app['session']->regenerateToken();

        $this->post('/login', ['_token' => $stale, 'email' => $player->email, 'password' => self::PASSWORD])
            ->assertStatus(419);

        $this->assertGuest('users');
    }






    // -----------------------------------------------------------
    // test_the_panels_lists_post_with_the_pages_token
    // -----------------------------------------------------------
    //
    // What Post_Link of custom_admin.js does with a delete
    // button: the token of the page's csrf-token meta tag,
    // sent as _token, carries the deletion through the check.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_panels_lists_post_with_the_pages_token(): void
    {
        $category = $this->makeCategory('Doomed');
        $this->signInAdmin($this->makeAdmin(true));
        preg_match('/<meta name="csrf-token" content="([^"]+)">/', $this->get('/control-panel/categories')->getContent(), $meta);

        $this->from('/control-panel/categories')->post("/control-panel/category/delete/{$category->id}", ['_token' => $meta[1]])
            ->assertRedirect('/control-panel/categories');

        $this->assertNull($category->fresh());
    }






    // -----------------------------------------------------------
    // test_a_vote_posts_with_the_pages_token
    // -----------------------------------------------------------
    //
    // What the vote script of custom.js does: the token of the
    // challenge page's csrf-token meta tag, sent as _token,
    // carries the vote through the check.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_vote_posts_with_the_pages_token(): void
    {
        $challenge = $this->makeChallenge();
        $this->signIn($this->makePlayer());
        preg_match('/<meta name="csrf-token" content="([^"]+)">/', $this->get("/user/challenge/{$challenge->public_id}")->getContent(), $meta);

        $this->post("/user/challenge/vote/up/{$challenge->public_id}", ['_token' => $meta[1] ?? 'none'])->assertOk();

        $this->assertSame(1, DB::table('cyber_challenges_votes')->where(['challenge_id' => $challenge->id, 'vote' => 'up'])->count());
    }






    // -----------------------------------------------------------
    // test_no_address_is_spared_the_token_check
    // -----------------------------------------------------------
    //
    // The token check spares no address of the lab, not even the
    // vote's bare address, which no route of its own answers: a
    // POST to it without the token ends on the 419 page for a
    // guest and a player alike, before any route can run.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_no_address_is_spared_the_token_check(): void
    {
        $this->post('/user/challenge/vote')->assertStatus(419);

        $this->signIn($this->makePlayer());
        $this->post('/user/challenge/vote')->assertStatus(419);
    }






    // -----------------------------------------------------------
    // test_a_post_from_the_sites_own_page_passes_without_the_token
    // -----------------------------------------------------------
    //
    // A POST the browser marks as coming from a page of the
    // site itself (Sec-Fetch-Site: same-origin, a header no
    // page's script can set) passes the check without the
    // token.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_post_from_the_sites_own_page_passes_without_the_token(): void
    {
        $player = $this->makePlayer();

        $this->signIn($player)->withHeader('Sec-Fetch-Site', 'same-origin')
            ->post('/user/feedback', ['feedback' => 'Sent from the site.'])
            ->assertRedirect();

        $this->assertSame(1, Feedback::where('user_id', $player->id)->count());
    }






    // -----------------------------------------------------------
    // test_a_post_from_another_site_needs_the_token
    // -----------------------------------------------------------
    //
    // A POST the browser marks as coming from another site —
    // another VU site under the same domain included
    // (same-site) — still needs the token, and without it ends
    // on the 419 page.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_post_from_another_site_needs_the_token(): void
    {
        $player = $this->makePlayer();
        $this->signIn($player);

        foreach (['cross-site', 'same-site', 'none'] as $site) {
            $this->withHeader('Sec-Fetch-Site', $site)->post('/user/feedback', ['feedback' => "Sent from {$site}."])->assertStatus(419);
        }

        $this->assertSame(0, Feedback::where('user_id', $player->id)->count());
    }
}
