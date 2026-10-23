<?php

// -----------------------------------------------------------
//  [*] RouteTableTest — the guards on every route
//
//  Pins the route table's protection as a whole, so that a
//  route added without a guard, or a guard dropped from a
//  route, fails here before it ships. Every control-panel
//  route but the two login pages needs an admin; every /user
//  route but the SSO logout needs a player; the main-only
//  routes, the ownership-checked routes and the guest-only
//  routes are each compared with the reviewed list below —
//  the lists hardened by the "Fix upload validation" commit
//  among them. Next to the declarations, every guarded route
//  is also requested for real by a guest and by a player
//  session, and must turn them away.
//
//  The routes the dev packages bring into the tests image
//  (_ignition/*) do not exist in production and are left
//  out.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature;

use Illuminate\Support\Facades\Route as Router;
use Illuminate\Support\Str;
use Tests\TestCase;








// -----------------------------------------------------------
// RouteTableTest
// -----------------------------------------------------------
//
// Routes are named "METHOD uri", with GET standing for the
// GET and HEAD pair.
//
//   routes — the application's routes by name
//   requestEach — requests routes with made-up parameters
//
// Used by:
//   - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------

final class RouteTableTest extends TestCase
{

    // The routes only a main admin may use, behind the perms
    // middleware
    private const MAIN_ONLY = [
        'GET control-panel/categories',
        'GET control-panel/category/delete/{uid}',
        'GET control-panel/challenges/status/{status}/{challenge_id}',
        'GET control-panel/dialog/category/edit/{uid}',
        'GET control-panel/dialog/category/new',
        'POST control-panel/dialog/category/store',
        'POST control-panel/dialog/category/update',
        'GET control-panel/dialog/emails/send_test/{email_id}',
        'GET control-panel/dialog/partners/edit/{uid}',
        'GET control-panel/dialog/partners/new',
        'POST control-panel/dialog/partners/store',
        'POST control-panel/dialog/partners/update',
        'POST control-panel/dialog/users/update',
        'GET control-panel/emails',
        'GET control-panel/emails/create',
        'GET control-panel/emails/delete/{uid}',
        'GET control-panel/emails/edit/{uid}',
        'GET control-panel/emails/send/{email_id}',
        'POST control-panel/emails/sending_test/{email_id}',
        'POST control-panel/emails/store',
        'GET control-panel/emails/sync/{type}',
        'POST control-panel/emails/update',
        'GET control-panel/pages',
        'GET control-panel/pages/create',
        'GET control-panel/pages/delete/{uid}',
        'GET control-panel/pages/edit/{uid}',
        'POST control-panel/pages/store',
        'POST control-panel/pages/update',
        'GET control-panel/partners',
        'GET control-panel/partners/delete/{uid}',
        'GET control-panel/settings',
        'POST control-panel/settings/event_data',
        'POST control-panel/settings/reg_open',
        'GET control-panel/users/delete/{uid}',
        'GET control-panel/users/status/{user_id}/{status_id}',
    ];

    // The routes a lab admin may only use on its own records,
    // with the r_exists check each one runs: the record's id
    // comes from the address (challenge_owned, group_owned)
    // or from the posted uid field (the _uid checks); a
    // member's or a group challenge's row id leads to the
    // group that is checked (membership_owned,
    // group_challenge_owned)
    private const OWNERSHIP_CHECKS = [
        'GET control-panel/challenges/delete/{uid}'                           => 'challenge_owned',
        'GET control-panel/challenges/edit/{uid}'                             => 'challenge_owned',
        'GET control-panel/challenges/files/delete/{uid}'                     => 'challenge_owned',
        'GET control-panel/challenges/files/{uid}'                            => 'challenge_owned',
        'POST control-panel/challenges/categories/store'                      => 'challenge_owned_uid',
        'POST control-panel/challenges/update'                                => 'challenge_owned_uid',
        'POST control-panel/challenges_file/store'                            => 'challenge_owned_uid',
        'POST control-panel/challenges_file/update'                           => 'challenge_owned_uid',
        'GET control-panel/dialog/users/vu-groups/challenge_add_{type}/{uid}' => 'group_owned',
        'GET control-panel/dialog/users/vu-groups/edit/{uid}'                 => 'group_owned',
        'GET control-panel/dialog/users/vu-groups/user_add_{type}/{uid}'      => 'group_owned',
        'GET control-panel/users/vu-groups/challenges_list/{uid}'             => 'group_owned',
        'GET control-panel/users/vu-groups/delete/{uid}'                      => 'group_owned',
        'GET control-panel/users/vu-groups/delete_challenge/{uid}'            => 'group_challenge_owned',
        'GET control-panel/users/vu-groups/delete_group/{uid}'                => 'membership_owned',
        'GET control-panel/users/vu-groups/leaderboard/{uid}'                 => 'group_owned',
        'GET control-panel/users/vu-groups/users_list/{uid}'                  => 'group_owned',
        'POST control-panel/dialog/users/vu-groups/challenges_store'          => 'group_owned_uid',
        'POST control-panel/dialog/users/vu-groups/update'                    => 'group_owned_uid',
        'POST control-panel/dialog/users/vu-groups/users_store'               => 'group_owned_uid',
    ];

    // The routes only a guest may use, behind the guest
    // middleware
    private const GUEST_ONLY = [
        'GET /',
        'GET control-panel',
        'GET forgot-password',
        'POST forgot-password',
        'POST login',
        'GET register',
        'POST register',
        'GET reset/{reset_token}',
        'GET verify/{verify_token}',
    ];

    // The routes with no guard at all, open to anyone on
    // purpose: the content pages, the SSO logout and the CSRF
    // cookie route Sanctum registers
    private const OPEN = [
        'GET page/{alias}',
        'GET sanctum/csrf-cookie',
        'GET user/logout_saml',
    ];






    // -----------------------------------------------------------
    // routes
    // -----------------------------------------------------------
    //
    // The application's routes keyed by name — the dev-only
    // _ignition routes left out — each with the middleware it
    // declares by name, as written in the routes file; the
    // closures the controllers register are dropped.
    //
    // Used by:
    //   - the tests below
    // -----------------------------------------------------------

    private function routes(): array
    {
        $routes = [];
        foreach (Router::getRoutes()->getRoutes() as $route) {
            if (Str::startsWith($route->uri(), '_ignition')) {
                continue;
            }
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $routes["{$method} {$route->uri()}"] = array_values(array_filter($route->gatherMiddleware(), 'is_string'));
            }
        }
        ksort($routes);
        return $routes;
    }






    // -----------------------------------------------------------
    // requestEach
    // -----------------------------------------------------------
    //
    // Requests every route named, with each parameter set to
    // 1, and asserts each one redirects to the given address.
    //
    // Used by:
    //   - the request tests below
    // -----------------------------------------------------------

    private function requestEach(array $names, string $redirect): void
    {
        foreach ($names as $name) {
            [$method, $uri] = explode(' ', $name, 2);
            $response = $this->call($method, '/' . preg_replace('/\{[^}]+\}/', '1', $uri));
            $this->assertSame(302, $response->getStatusCode(), $name);
            $this->assertSame(url($redirect), $response->headers->get('Location'), $name);
        }
    }






    // -----------------------------------------------------------
    // test_every_control_panel_route_needs_an_admin
    // -----------------------------------------------------------
    //
    // Apart from the admin login page and the SSO login, every
    // control-panel route declares the admins guard.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_every_control_panel_route_needs_an_admin(): void
    {
        foreach ($this->routes() as $name => $middleware) {
            if (preg_match('#^\w+ control-panel/#', $name) && $name !== 'GET control-panel/login') {
                $this->assertContains('auth.admin:admins', $middleware, $name);
            }
        }
    }






    // -----------------------------------------------------------
    // test_every_player_route_needs_a_player
    // -----------------------------------------------------------
    //
    // Apart from the SSO logout, every /user route declares
    // the users guard.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_every_player_route_needs_a_player(): void
    {
        foreach ($this->routes() as $name => $middleware) {
            if (preg_match('#^\w+ user/#', $name) && $name !== 'GET user/logout_saml') {
                $this->assertContains('auth:users', $middleware, $name);
            }
        }
    }






    // -----------------------------------------------------------
    // test_the_main_only_routes_are_the_reviewed_ones
    // -----------------------------------------------------------
    //
    // The routes behind the perms middleware are exactly the
    // MAIN_ONLY list.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_main_only_routes_are_the_reviewed_ones(): void
    {
        $actual = array_keys(array_filter($this->routes(), fn (array $middleware) => in_array('perms', $middleware, true)));

        $this->assertEqualsCanonicalizing(self::MAIN_ONLY, $actual);
    }






    // -----------------------------------------------------------
    // test_the_ownership_checks_are_the_reviewed_ones
    // -----------------------------------------------------------
    //
    // The routes behind the r_exists middleware, and the check
    // each one runs, are exactly the OWNERSHIP_CHECKS list.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_ownership_checks_are_the_reviewed_ones(): void
    {
        $actual = [];
        foreach ($this->routes() as $name => $middleware) {
            foreach ($middleware as $entry) {
                if (Str::startsWith($entry, 'r_exists:')) {
                    $actual[$name] = Str::after($entry, 'r_exists:');
                }
            }
        }
        ksort($actual);
        $wanted = self::OWNERSHIP_CHECKS;
        ksort($wanted);

        $this->assertSame($wanted, $actual);
    }






    // -----------------------------------------------------------
    // test_the_guest_only_routes_are_the_reviewed_ones
    // -----------------------------------------------------------
    //
    // The routes behind the guest middleware are exactly the
    // GUEST_ONLY list.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_guest_only_routes_are_the_reviewed_ones(): void
    {
        $actual = array_keys(array_filter($this->routes(), fn (array $middleware) => in_array('guest', $middleware, true)));

        $this->assertEqualsCanonicalizing(self::GUEST_ONLY, $actual);
    }






    // -----------------------------------------------------------
    // test_no_route_is_left_open_by_accident
    // -----------------------------------------------------------
    //
    // Every route declares a guard — admin, player, guest,
    // Sanctum or one of the SSO logins — except the OPEN list.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_no_route_is_left_open_by_accident(): void
    {
        $guards = ['auth.admin:admins', 'auth:users', 'guest', 'auth:sanctum', 'saml_auth', 'saml_auth_admin'];

        $open = array_keys(array_filter($this->routes(), fn (array $middleware) => !array_intersect($guards, $middleware)));

        $this->assertEqualsCanonicalizing(self::OPEN, $open);
    }






    // -----------------------------------------------------------
    // test_every_admin_route_turns_guests_away
    // -----------------------------------------------------------
    //
    // Requested for real, every control-panel route behind the
    // admins guard sends a guest to the admin login page.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_every_admin_route_turns_guests_away(): void
    {
        $guarded = array_keys(array_filter($this->routes(), fn (array $middleware) => in_array('auth.admin:admins', $middleware, true)));

        $this->requestEach($guarded, '/control-panel');
    }






    // -----------------------------------------------------------
    // test_a_player_session_opens_no_admin_route
    // -----------------------------------------------------------
    //
    // A signed-in player is turned away from every
    // control-panel route behind the admins guard just like a
    // guest.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_player_session_opens_no_admin_route(): void
    {
        $guarded = array_keys(array_filter($this->routes(), fn (array $middleware) => in_array('auth.admin:admins', $middleware, true)));
        $this->signIn($this->makePlayer());

        $this->requestEach($guarded, '/control-panel');
    }






    // -----------------------------------------------------------
    // test_every_player_route_turns_guests_away
    // -----------------------------------------------------------
    //
    // Requested for real, every route behind the users guard
    // sends a guest to the login form.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_every_player_route_turns_guests_away(): void
    {
        $guarded = array_keys(array_filter($this->routes(), fn (array $middleware) => in_array('auth:users', $middleware, true)));

        $this->requestEach($guarded, '/');
    }






    // -----------------------------------------------------------
    // test_an_admin_session_opens_no_player_route
    // -----------------------------------------------------------
    //
    // A signed-in admin is no player: every route behind the
    // users guard sends it to the login form too.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_admin_session_opens_no_player_route(): void
    {
        $guarded = array_keys(array_filter($this->routes(), fn (array $middleware) => in_array('auth:users', $middleware, true)));
        $this->signInAdmin($this->makeAdmin(true));

        $this->requestEach($guarded, '/');
    }






    // -----------------------------------------------------------
    // test_the_api_user_route_turns_anonymous_clients_away
    // -----------------------------------------------------------
    //
    // The skeleton's GET /api/user answers an anonymous client
    // with a 401: Sanctum's guard is the players' users guard,
    // which finds no one, as the api group starts no session.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_api_user_route_turns_anonymous_clients_away(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }
}
