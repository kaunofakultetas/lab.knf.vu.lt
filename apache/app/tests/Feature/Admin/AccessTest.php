<?php

// -----------------------------------------------------------
//  [*] AccessTest — who gets into the control panel, and how far
//
//  The control panel has two kinds of admin. A main admin may
//  do everything. Any other admin — a lab teacher — signs in
//  the same way but is kept off the routes behind the perms
//  middleware (categories, partners, pages, emails, settings,
//  user status and deletion, challenge visibility), which
//  send it back where it came from with an error, and the
//  sidebar hides those menus from it. Guests, and players
//  signed in on the player side, are sent to the admin login
//  page, which offers the VU SSO only.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature\Admin;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;








// -----------------------------------------------------------
// AccessTest
// -----------------------------------------------------------
//
// The behaviour of every single route behind auth.admin is
// RouteTableTest's; this class follows the kinds of visitor.
//
// Used by:
//   - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------

final class AccessTest extends TestCase
{

    // The refusal the perms and r_exists middleware flash
    private const REFUSAL = "You don't have permissions to do this!";






    // -----------------------------------------------------------
    // test_the_admin_login_page_offers_only_the_sso
    // -----------------------------------------------------------
    //
    // The admin login page links the SSO login route and has
    // no password form.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_admin_login_page_offers_only_the_sso(): void
    {
        $response = $this->get('/control-panel');

        $response->assertOk();
        $response->assertViewIs('admins.login');
        $response->assertSee('href="https://lab.knf.vu.lt/control-panel/login"', false);
        $response->assertDontSee('type="password"', false);
    }






    // -----------------------------------------------------------
    // test_guests_are_sent_to_the_admin_login
    // -----------------------------------------------------------
    //
    // A guest asking for an admin page lands on the admin
    // login page.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_guests_are_sent_to_the_admin_login(): void
    {
        $this->get('/control-panel/dashboard')->assertRedirect('/control-panel');
        $this->get('/control-panel/challenges')->assertRedirect('/control-panel');
        $this->post('/control-panel/settings/reg_open', ['reg_open' => 1])->assertRedirect('/control-panel');
    }






    // -----------------------------------------------------------
    // test_a_player_session_does_not_open_the_control_panel
    // -----------------------------------------------------------
    //
    // Being signed in as a player counts for nothing on the
    // admin side.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_player_session_does_not_open_the_control_panel(): void
    {
        $this->signIn($this->makePlayer());

        $this->get('/control-panel/dashboard')->assertRedirect('/control-panel');
        $this->get('/control-panel/users')->assertRedirect('/control-panel');
    }






    // -----------------------------------------------------------
    // test_a_main_admin_opens_the_main_only_pages
    // -----------------------------------------------------------
    //
    // Every page behind the perms middleware opens for a main
    // admin.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    #[DataProvider('mainOnlyPages')]
    public function test_a_main_admin_opens_the_main_only_pages(string $page): void
    {
        $this->signInAdmin($this->makeAdmin(true));

        $this->get($page)->assertOk();
    }






    // -----------------------------------------------------------
    // test_a_lab_admin_is_sent_back_from_the_main_only_pages
    // -----------------------------------------------------------
    //
    // The same pages send a lab admin back to the page it came
    // from with the permissions error.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    #[DataProvider('mainOnlyPages')]
    public function test_a_lab_admin_is_sent_back_from_the_main_only_pages(string $page): void
    {
        $this->signInAdmin($this->makeAdmin());

        $this->from('/control-panel/dashboard')->get($page)->assertRedirect('/control-panel/dashboard');

        $this->assertErrorFlashed(self::REFUSAL);
    }






    // -----------------------------------------------------------
    // mainOnlyPages
    // -----------------------------------------------------------
    //
    // The pages behind the perms middleware that open without
    // naming a record.
    //
    // Used by:
    //   - test_a_main_admin_opens_the_main_only_pages,
    //     test_a_lab_admin_is_sent_back_from_the_main_only_pages
    //     (above)
    // -----------------------------------------------------------

    public static function mainOnlyPages(): array
    {
        return [
            'categories'         => ['/control-panel/categories'],
            'partners'           => ['/control-panel/partners'],
            'new partner dialog' => ['/control-panel/dialog/partners/new'],
            'settings'           => ['/control-panel/settings'],
            'pages'              => ['/control-panel/pages'],
            'new page'           => ['/control-panel/pages/create'],
            'emails'             => ['/control-panel/emails'],
            'new email'          => ['/control-panel/emails/create'],
        ];
    }






    // -----------------------------------------------------------
    // test_both_kinds_of_admin_share_the_rest
    // -----------------------------------------------------------
    //
    // The dashboard, the challenge and user lists and the VU
    // groups are open to lab admins as well.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_both_kinds_of_admin_share_the_rest(): void
    {
        $this->signInAdmin($this->makeAdmin());

        $this->get('/control-panel/dashboard')->assertOk();
        $this->get('/control-panel/challenges')->assertOk();
        $this->get('/control-panel/challenges/create')->assertOk();
        $this->get('/control-panel/users')->assertOk();
        $this->get('/control-panel/users/vu-groups')->assertOk();
    }






    // -----------------------------------------------------------
    // test_the_sidebar_shows_main_only_menus_to_main_admins
    // -----------------------------------------------------------
    //
    // A main admin's sidebar links categories, pages, emails
    // and settings, under the admin's VU user id.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_sidebar_shows_main_only_menus_to_main_admins(): void
    {
        $admin = $this->makeAdmin(true);

        $response = $this->signInAdmin($admin)->get('/control-panel/dashboard');

        $response->assertSee("<h1 class=\"h4 mb-2\">{$admin->vu_id}</h1>", false);
        $response->assertSee('href="https://lab.knf.vu.lt/control-panel/categories"', false);
        $response->assertSee('href="https://lab.knf.vu.lt/control-panel/pages"', false);
        $response->assertSee('href="https://lab.knf.vu.lt/control-panel/emails"', false);
        $response->assertSee('href="https://lab.knf.vu.lt/control-panel/settings"', false);
    }






    // -----------------------------------------------------------
    // test_the_sidebar_hides_main_only_menus_from_lab_admins
    // -----------------------------------------------------------
    //
    // A lab admin's sidebar keeps the shared menus and leaves
    // the main-only ones out.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_sidebar_hides_main_only_menus_from_lab_admins(): void
    {
        $response = $this->signInAdmin($this->makeAdmin())->get('/control-panel/dashboard');

        $response->assertSee('href="https://lab.knf.vu.lt/control-panel/challenges"', false);
        $response->assertSee('href="https://lab.knf.vu.lt/control-panel/users/vu-groups"', false);
        $response->assertDontSee('href="https://lab.knf.vu.lt/control-panel/categories"', false);
        $response->assertDontSee('href="https://lab.knf.vu.lt/control-panel/pages"', false);
        $response->assertDontSee('href="https://lab.knf.vu.lt/control-panel/emails"', false);
        $response->assertDontSee('href="https://lab.knf.vu.lt/control-panel/settings"', false);
    }






    // -----------------------------------------------------------
    // test_a_demoted_admin_loses_the_main_pages_at_once
    // -----------------------------------------------------------
    //
    // Main-admin rights taken away end at once: the next request
    // of the still signed-in admin to a main-only page is turned
    // back, as the checks read the account, not the session.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_demoted_admin_loses_the_main_pages_at_once(): void
    {
        $admin = $this->makeAdmin(true);
        $this->signInAdmin($admin)->from('/control-panel/dashboard');
        $admin->update(['main' => 0]);

        $response = $this->get('/control-panel/settings');

        $response->assertRedirect('/control-panel/dashboard');
        $this->assertErrorFlashed(self::REFUSAL);
    }






    // -----------------------------------------------------------
    // test_panel_pages_carry_the_csrf_token
    // -----------------------------------------------------------
    //
    // The pages whose lists delete and switch things carry the
    // session's CSRF token in a csrf-token meta tag, which
    // Post_Link of custom_admin.js sends with every change it
    // posts, and load that script under an address carrying
    // the file's time, so a browser cannot keep a copy older
    // than the page.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_panel_pages_carry_the_csrf_token(): void
    {
        $this->signInAdmin($this->makeAdmin(true));
        $script = '/assets/js/app_admin.min.js?' . filemtime(public_path('assets/js/app_admin.min.js'));

        foreach (['/control-panel/categories', '/control-panel/users', '/control-panel/challenges', '/control-panel/emails'] as $page) {
            $response = $this->get($page);
            $response->assertSee('<meta name="csrf-token" content="' . csrf_token() . '">', false);
            $response->assertSee('<script src="' . $script . '"></script>', false);
        }
    }






    // -----------------------------------------------------------
    // test_signing_out_of_the_panel_is_never_a_plain_link
    // -----------------------------------------------------------
    //
    // The top bar's sign-out posts; opened as a link, which
    // another site can make an admin follow, the address
    // answers 405 and the admin stays signed in.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_signing_out_of_the_panel_is_never_a_plain_link(): void
    {
        $admin = $this->makeAdmin(true);
        $this->signInAdmin($admin);

        $this->get('/control-panel/dashboard')->assertSee("onClick=\"Post_Link('https://lab.knf.vu.lt/control-panel/logout');\"", false);
        $this->get('/control-panel/logout')->assertStatus(405);

        $this->assertAuthenticatedAs($admin, 'admins');
    }
}
