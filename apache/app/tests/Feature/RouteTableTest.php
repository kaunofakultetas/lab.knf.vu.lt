<?php

// -----------------------------------------------------------
//  [*] RouteTableTest — the guards on every route
//
//  Pins the route table's protection as a whole, so that a
//  route added without a guard, or a guard dropped from a
//  route, fails here before it ships. Every control-panel
//  route but the two login pages and the sign-out needs an
//  admin; every /user route but the SSO logout needs a
//  player; the main-only routes, the ownership-checked
//  routes and the guest-only routes are each compared with
//  the reviewed list below — the lists hardened by the "Fix
//  upload validation" commit among them. Next to the
//  declarations, every guarded route is also requested for
//  real by a guest and by a session of the other side — a
//  player at the control panel, an admin at the player
//  pages — and must turn them away; the API's one route
//  turns an anonymous client away; and every change the
//  control panel makes from its lists must refuse to happen
//  by a plain link.
//
//  The routes the dev packages bring into the tests image
//  (_ignition/*) do not exist in production and are left
//  out.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature;

use App\Http\Middleware\Authenticate;
use App\Models\ChallengesFiles;
use App\Models\Emails;
use App\Models\GroupsUsers;
use App\Models\Pages;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Mail;
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
        'POST control-panel/category/delete/{uid}',
        'POST control-panel/challenges/status/{status}/{challenge_id}',
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
        'POST control-panel/emails/delete/{uid}',
        'GET control-panel/emails/edit/{uid}',
        'POST control-panel/emails/send/{email_id}',
        'POST control-panel/emails/sending_test/{email_id}',
        'POST control-panel/emails/store',
        'POST control-panel/emails/sync/{type}',
        'POST control-panel/emails/update',
        'GET control-panel/pages',
        'GET control-panel/pages/create',
        'POST control-panel/pages/delete/{uid}',
        'GET control-panel/pages/edit/{uid}',
        'POST control-panel/pages/store',
        'POST control-panel/pages/update',
        'GET control-panel/partners',
        'POST control-panel/partners/delete/{uid}',
        'GET control-panel/settings',
        'POST control-panel/settings/reg_open',
        'POST control-panel/users/delete/{uid}',
        'POST control-panel/users/status/{user_id}/{status_id}',
    ];

    // The routes a lab admin may only use on its own records,
    // with the r_exists check each one runs: the record's id
    // comes from the address (challenge_owned, group_owned)
    // or from the posted uid field (the _uid checks); a
    // member's or a group challenge's row id leads to the
    // group that is checked (membership_owned,
    // group_challenge_owned), a file link's id to its
    // challenge (file_owned, file_owned_uid)
    private const OWNERSHIP_CHECKS = [
        'POST control-panel/challenges/delete/{uid}'                          => 'challenge_owned',
        'GET control-panel/challenges/edit/{uid}'                             => 'challenge_owned',
        'POST control-panel/challenges/files/delete/{uid}'                    => 'file_owned',
        'GET control-panel/challenges/files/{uid}'                            => 'challenge_owned',
        'POST control-panel/challenges/categories/store'                      => 'challenge_owned_uid',
        'POST control-panel/challenges/update'                                => 'challenge_owned_uid',
        'POST control-panel/challenges_file/store'                            => 'challenge_owned_uid',
        'POST control-panel/challenges_file/update'                           => 'file_owned_uid',
        'GET control-panel/dialog/challenge_file/edit/{uid}'                  => 'file_owned',
        'GET control-panel/dialog/challenge_file/new/{uid}'                   => 'challenge_owned',
        'GET control-panel/dialog/challenges/categories/{uid}'                => 'challenge_owned',
        'GET control-panel/dialog/users/vu-groups/challenge_add_{type}/{uid}' => 'group_owned',
        'GET control-panel/dialog/users/vu-groups/edit/{uid}'                 => 'group_owned',
        'GET control-panel/dialog/users/vu-groups/user_add_{type}/{uid}'      => 'group_owned',
        'GET control-panel/users/vu-groups/challenges_list/{uid}'             => 'group_owned',
        'POST control-panel/users/vu-groups/delete/{uid}'                     => 'group_owned',
        'POST control-panel/users/vu-groups/delete_challenge/{uid}'           => 'group_challenge_owned',
        'POST control-panel/users/vu-groups/delete_group/{uid}'               => 'membership_owned',
        'GET control-panel/users/vu-groups/leaderboard/{uid}'                 => 'group_owned',
        'GET control-panel/users/vu-groups/users_list/{uid}'                  => 'group_owned',
        'POST control-panel/dialog/users/vu-groups/challenges_store'          => 'group_owned_uid',
        'POST control-panel/dialog/users/vu-groups/update'                    => 'group_owned_uid',
        'POST control-panel/dialog/users/vu-groups/users_store'               => 'group_owned_uid',
    ];

    // The routes only a guest may use, behind the guest
    // middleware; the control panel's login page counts only
    // admin sessions (guest:admins), so a signed-in player
    // still reaches it
    private const GUEST_ONLY = [
        'GET /',
        'GET control-panel',
        'GET forgot-password',
        'POST forgot-password',
        'POST login',
        'GET register',
        'POST register',
        'GET reset/{reset_token}',
        'POST reset/{reset_token}',
        'GET verify/{verify_token}',
        'POST verify/{verify_token}',
    ];

    // The routes with no guard at all, open to anyone on
    // purpose: the content pages, the two SSO logouts — which
    // must work once the session has ended — and the CSRF
    // cookie route Sanctum registers
    private const OPEN = [
        'GET page/{alias}',
        'GET sanctum/csrf-cookie',
        'POST control-panel/logout',
        'POST user/logout_saml',
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
    // Apart from the admin login page, the SSO login and the
    // sign-out, every control-panel route declares the admins
    // guard.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_every_control_panel_route_needs_an_admin(): void
    {
        foreach ($this->routes() as $name => $middleware) {
            if (preg_match('#^\w+ control-panel/#', $name) && !in_array($name, ['GET control-panel/login', 'POST control-panel/logout'], true)) {
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
            if (preg_match('#^\w+ user/#', $name) && $name !== 'POST user/logout_saml') {
                $this->assertContains('auth:users', $middleware, $name);
            }
        }
    }






    // -----------------------------------------------------------
    // test_only_player_routes_check_the_players_password
    // -----------------------------------------------------------
    //
    // AuthenticateSession, which signs a player out once the
    // account's password has changed elsewhere, runs after the
    // users guard on every route that has it, and on no other,
    // the web group included: on a route without the guard it
    // would check whichever player is signed in on the browser,
    // and could end the session before an admin's or a
    // player's VU sign-out ran. Each route is read with its
    // groups and names resolved, as the router runs it.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_only_player_routes_check_the_players_password(): void
    {
        foreach (Router::getRoutes()->getRoutes() as $route) {
            if (Str::startsWith($route->uri(), '_ignition')) {
                continue;
            }
            $resolved = Router::gatherRouteMiddleware($route);
            $guard = array_search(Authenticate::class . ':users', $resolved, true);
            $check = array_search(AuthenticateSession::class, $resolved, true);

            $this->assertSame($guard !== false, $check !== false, $route->uri());
            $this->assertTrue($check === false || $check > $guard, $route->uri());
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
    // The routes behind the guest middleware, whatever guards
    // they name, are exactly the GUEST_ONLY list.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_guest_only_routes_are_the_reviewed_ones(): void
    {
        $actual = array_keys(array_filter($this->routes(), fn (array $middleware) => (bool) preg_grep('/^guest(:|$)/', $middleware)));

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
        $guards = ['auth.admin:admins', 'auth:users', 'guest', 'guest:admins', 'auth:sanctum', 'saml_auth', 'saml_auth_admin'];

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






    // -----------------------------------------------------------
    // test_a_plain_link_never_changes_anything
    // -----------------------------------------------------------
    //
    // Every change the control panel makes from its lists —
    // deleting a category, partner, page, mail template,
    // account, challenge, file link or group, removing a group
    // member or challenge, switching an account or a
    // challenge, sending a template to the whole list — is a
    // POST. The same address opened as a link, which another
    // site can make a signed-in admin follow, answers 405 and
    // changes nothing; outside the tests the POST needs the
    // CSRF token, which no other site has.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_plain_link_never_changes_anything(): void
    {
        Mail::fake();
        $_SERVER['MAILJET_REGISTERED_LIST'] = 'registered@lists.test';
        $category = $this->makeCategory('Kept Category');
        $partner = $this->makePartner();
        $page = Pages::create(['name' => 'Rules', 'content' => '<p>Rules</p>', 'alias' => 'rules']);
        $template = Emails::create(['name' => 'Kept', 'subject' => 'Kept', 'content' => '<p>Kept</p>', 'sent' => 0]);
        $unsent = Emails::create(['name' => 'Unsent', 'subject' => 'Unsent', 'content' => '<p>Unsent</p>', 'sent' => 0]);
        $account = $this->makePlayer();
        $active = $this->makePlayer();
        $challenge = $this->makeChallenge();
        $visible = $this->makeChallenge();
        $file = ChallengesFiles::create(['challenge_id' => $this->makeChallenge()->id, 'name' => 'kept.pcap', 'file_url' => 'https://files.test/kept']);
        $group = $this->makeGroup();
        $other = $this->makeGroup();
        $membership = GroupsUsers::create(['group_id' => $other->id, 'vu_id' => 's1234567']);
        $place = $this->assignToGroup($other, $this->makeChallenge());
        $links = [
            'delete a category'        => ["/control-panel/category/delete/{$category->id}", fn () => $category->fresh() !== null],
            'delete a partner'         => ["/control-panel/partners/delete/{$partner->id}", fn () => $partner->fresh() !== null],
            'delete a page'            => ["/control-panel/pages/delete/{$page->id}", fn () => $page->fresh() !== null],
            'delete a mail template'   => ["/control-panel/emails/delete/{$template->id}", fn () => $template->fresh() !== null],
            'send a template to all'   => ["/control-panel/emails/send/{$unsent->id}", fn () => (int) $unsent->fresh()->sent === 0],
            'delete an account'        => ["/control-panel/users/delete/{$account->id}", fn () => $account->fresh() !== null],
            'switch an account off'    => ["/control-panel/users/status/{$active->id}/1", fn () => $active->fresh()->status === 1],
            'delete a challenge'       => ["/control-panel/challenges/delete/{$challenge->id}", fn () => $challenge->fresh() !== null],
            'hide a challenge'         => ["/control-panel/challenges/status/1/{$visible->id}", fn () => $visible->fresh()->hidden === 0],
            'delete a file link'       => ["/control-panel/challenges/files/delete/{$file->id}", fn () => $file->fresh() !== null],
            'delete a group'           => ["/control-panel/users/vu-groups/delete/{$group->id}", fn () => $group->fresh() !== null],
            'remove a group member'    => ["/control-panel/users/vu-groups/delete_group/{$membership->id}", fn () => $membership->fresh() !== null],
            'remove a group challenge' => ["/control-panel/users/vu-groups/delete_challenge/{$place->id}", fn () => $place->fresh() !== null],
        ];
        $this->signInAdmin($this->makeAdmin(true))->from('/control-panel/dashboard');

        foreach ($links as $name => [$link]) {
            $this->assertSame(405, $this->get($link)->getStatusCode(), $name);
        }
        unset($_SERVER['MAILJET_REGISTERED_LIST']);

        $changed = array_keys(array_filter($links, fn (array $link) => !$link[1]()));
        $this->assertSame([], $changed, 'plain links that changed data: ' . implode(', ', $changed));
        Mail::assertNothingSent();
    }






    // -----------------------------------------------------------
    // test_every_id_in_an_address_is_a_plain_number
    // -----------------------------------------------------------
    //
    // Every route parameter that names a record by its id
    // takes a plain whole number only — digits without a
    // leading zero — so other text in its place is a 404
    // before any check reads it: MySQL reads text that starts
    // with a number as that number, and the pages' scripts
    // read a number with a leading zero as an octal one. The
    // parameters that carry no id are listed here, so a new
    // one has to be placed on one side or the other.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_every_id_in_an_address_is_a_plain_number(): void
    {
        $notIds = ['public_id', 'public_group_id', 'alias', 'reset_token', 'verify_token', 'type', 'status', 'status_id'];
        $ids = 0;

        foreach (Router::getRoutes() as $route) {
            foreach (array_diff($route->parameterNames(), $notIds) as $name) {
                $this->assertSame('[1-9][0-9]*', $route->wheres[$name] ?? null, "{$route->uri()}: {$name}");
                $ids++;
            }
        }

        $this->assertGreaterThan(0, $ids);
    }
}
