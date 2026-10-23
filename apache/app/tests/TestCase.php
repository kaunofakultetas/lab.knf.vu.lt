<?php

// -----------------------------------------------------------
//  [*] TestCase — what every application test starts from
//
//  The base class of the Feature, Http and Defects suites.
//  Each test gets a fresh application (CreatesApplication),
//  runs inside a database transaction that is rolled back
//  when it ends, and starts from an empty Redis — so every
//  test sees the database exactly as mysql/init.sql leaves
//  it: the two settings rows (the old event range,
//  registration open), the seeded main admin with id 1, the
//  seven categories, and nothing else.
//
//  On top of that it carries the builders the tests share —
//  accounts, content, groups, solves — and the sign-in,
//  reCAPTCHA and flash-message helpers, so that a test spells
//  out only what is particular to it.
//
//  Used by:
//    - every test class under tests/Feature, tests/Http and
//      tests/Defects
// -----------------------------------------------------------


namespace Tests;

use App\Models\Admin;
use App\Models\Categories;
use App\Models\Challenges;
use App\Models\ChallengesHistory;
use App\Models\ChallengesSubmitted;
use App\Models\Groups;
use App\Models\GroupsChallenges;
use App\Models\GroupsUsers;
use App\Models\Partners;
use App\Models\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Lunaweb\RecaptchaV3\Facades\RecaptchaV3;








// -----------------------------------------------------------
// TestCase
// -----------------------------------------------------------
//
// Database transactions roll every test back, so a test may
// create, update and delete freely; ids still climb from
// test to test, as MySQL never hands an auto-increment value
// out twice. Redis is flushed instead, as it has no
// transactions. Players and admins sign in through the
// guards the routes use — users and admins.
//
//   makePlayer, makeSsoPlayer, makeAdmin — accounts
//   makePartner, makeCategory, makeChallenge — content
//   makeGroup, joinGroup, assignToGroup — VU groups (labs)
//   recordSolve — a solve, booked the way the app books it
//   signIn, signInAdmin — sessions
//   fakeRecaptcha — the reCAPTCHA verdict
//   setRegistrationOpen, setEventRange — the settings rows
//   assertErrorFlashed — the error toast of the next page
//
// Used by:
//   - every Feature, Http and Defects test class
// -----------------------------------------------------------

abstract class TestCase extends BaseTestCase
{

    use CreatesApplication;
    use DatabaseTransactions;

    // The password every account made by the builders logs in
    // with
    public const PASSWORD = 'correct-horse-battery';

    // The token every faked reCAPTCHA check expects to receive
    // from the form
    public const RECAPTCHA_TOKEN = 'recaptcha-test-token';






    // -----------------------------------------------------------
    // setUp
    // -----------------------------------------------------------
    //
    // Empties the throwaway Redis, where the ranking table,
    // the solve history and the votes live, and gives the
    // request globals the client address Apache always sets:
    // SettingsController::getUserIP reads $_SERVER directly
    // rather than the request, and the PHP command line has
    // no REMOTE_ADDR of its own. The proxy headers it trusts
    // are cleared, so each test sets the ones it needs.
    //
    // Used by:
    //   - PHPUnit, before every test
    // -----------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        unset($_SERVER['HTTP_CLIENT_IP'], $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_CF_CONNECTING_IP']);
    }






    // -----------------------------------------------------------
    // tearDown
    // -----------------------------------------------------------
    //
    // After Laravel's own teardown, takes off the error and
    // exception handlers the test's application installed,
    // which Laravel 10.6 leaves in place. Left there, they make
    // PHPUnit remove Laravel's handler instead of its own when
    // the test ends, and PHPUnit's stays active outside any
    // test, where the first warning makes it throw: in an
    // isolated test that is the child's rewind of STDOUT —
    // the real pipe on PHP 8.4 — so the child ends before it
    // can report. A test that never booted the application
    // has nothing to take off.
    //
    // Used by:
    //   - PHPUnit, after every test
    // -----------------------------------------------------------

    protected function tearDown(): void
    {
        $booted = $this->app !== null;

        parent::tearDown();

        if ($booted) {
            restore_error_handler();
            restore_exception_handler();
        }
    }






    // -----------------------------------------------------------
    // makePlayer
    // -----------------------------------------------------------
    //
    // A verified local account — registered through the form,
    // not the VU SSO, so it has no vu_id — with no points,
    // logging in with PASSWORD. The username and email are
    // unique per call; the attributes given override any of
    // the defaults.
    //
    // Used by:
    //   - makeSsoPlayer (below)
    //   - the Feature tests that need a player
    // -----------------------------------------------------------

    protected function makePlayer(array $attributes = []): User
    {
        $tag = Str::lower(Str::random(10));
        return User::create(array_merge([
            'user_type'  => 1,
            'username'   => "player_{$tag}",
            'email'      => "{$tag}@players.test",
            'password'   => Hash::make(self::PASSWORD),
            'status'     => 1,
            'newsletter' => 0,
            'points'     => 0,
        ], $attributes));
    }






    // -----------------------------------------------------------
    // makeSsoPlayer
    // -----------------------------------------------------------
    //
    // An account as the VU SSO login provisions it: the VU
    // user id as both vu_id and username, the full name in
    // display_name, active from the start. Only such accounts
    // can belong to a VU group, as groups list vu_ids.
    //
    // Used by:
    //   - the Feature tests that need a VU account
    // -----------------------------------------------------------

    protected function makeSsoPlayer(array $attributes = []): User
    {
        $uid = 's' . random_int(1000000, 9999999);
        return $this->makePlayer(array_merge([
            'vu_id'        => $uid,
            'username'     => $uid,
            'display_name' => 'Vardenis Pavardenis',
            'email'        => "{$uid}@sso.test",
        ], $attributes));
    }






    // -----------------------------------------------------------
    // makeAdmin
    // -----------------------------------------------------------
    //
    // A control-panel account. A main admin may do everything;
    // any other admin (a lab teacher) is kept off the routes
    // behind the perms middleware and may only change the
    // challenges and groups it created itself.
    //
    // Used by:
    //   - the Feature tests that need an admin
    // -----------------------------------------------------------

    protected function makeAdmin(bool $main = false, array $attributes = []): Admin
    {
        $tag = Str::lower(Str::random(8));
        return Admin::create(array_merge([
            'vu_id'    => "admin_{$tag}",
            'email'    => "{$tag}@admins.test",
            'password' => Hash::make(self::PASSWORD),
            'main'     => $main ? 1 : 0,
        ], $attributes));
    }






    // -----------------------------------------------------------
    // makePartner
    // -----------------------------------------------------------
    //
    // The organisation a challenge is credited to, with a
    // picture file name as the partner upload stores it. The
    // file itself never exists — only the upload tests write
    // real ones.
    //
    // Used by:
    //   - makeChallenge (below)
    //   - the partner, challenge and route-table tests
    // -----------------------------------------------------------

    protected function makePartner(array $attributes = []): Partners
    {
        return Partners::create(array_merge([
            'name'    => 'Partner ' . Str::random(6),
            'picture' => Str::lower(Str::random(32)) . '.png',
        ], $attributes));
    }






    // -----------------------------------------------------------
    // makeCategory
    // -----------------------------------------------------------
    //
    // A challenge category next to the seven init.sql seeds.
    //
    // Used by:
    //   - the dashboard, admin category, CSRF and route-table
    //     tests
    // -----------------------------------------------------------

    protected function makeCategory(string $name): Categories
    {
        return Categories::create(['name' => $name]);
    }






    // -----------------------------------------------------------
    // makeChallenge
    // -----------------------------------------------------------
    //
    // A visible ten-point challenge with its own partner and a
    // random answer, created by the seeded main admin. The
    // answer is stored bare, without the VU{...} wrapper
    // players type around it.
    //
    // Used by:
    //   - most Feature tests
    // -----------------------------------------------------------

    protected function makeChallenge(array $attributes = []): Challenges
    {
        if (!isset($attributes['partner_id'])) {
            $attributes['partner_id'] = $this->makePartner()->id;
        }
        return Challenges::create(array_merge([
            'public_id'  => (string) Str::uuid(),
            'created_by' => 1,
            'name'       => 'Challenge ' . Str::random(6),
            'descr'      => 'Find the flag hidden in the attached capture.',
            'content'    => '<p>The flag is somewhere in <b>capture.pcap</b>.</p>',
            'answer'     => 'flag_' . Str::lower(Str::random(10)),
            'points'     => 10,
            'hidden'     => 0,
        ], $attributes));
    }






    // -----------------------------------------------------------
    // makeGroup
    // -----------------------------------------------------------
    //
    // A VU group (a lab), owned by the given admin or by the
    // seeded main admin.
    //
    // Used by:
    //   - the Feature tests that involve a VU group
    // -----------------------------------------------------------

    protected function makeGroup(?Admin $owner = null, array $attributes = []): Groups
    {
        return Groups::create(array_merge([
            'public_id'  => (string) Str::uuid(),
            'created_by' => $owner ? $owner->id : 1,
            'name'       => 'Lab ' . Str::random(6),
            'created'    => date('Y-m-d H:i:s'),
        ], $attributes));
    }






    // -----------------------------------------------------------
    // joinGroup
    // -----------------------------------------------------------
    //
    // Makes the player a member of the group. Membership is
    // stored by vu_id, so only SSO accounts can really join.
    //
    // Used by:
    //   - the Feature tests that involve a VU group
    // -----------------------------------------------------------

    protected function joinGroup(Groups $group, User $player): GroupsUsers
    {
        return GroupsUsers::create(['group_id' => $group->id, 'vu_id' => $player->vu_id]);
    }






    // -----------------------------------------------------------
    // assignToGroup
    // -----------------------------------------------------------
    //
    // Puts the challenge on the group's dashboard.
    //
    // Used by:
    //   - the Feature tests that involve a VU group
    // -----------------------------------------------------------

    protected function assignToGroup(Groups $group, Challenges $challenge): GroupsChallenges
    {
        return GroupsChallenges::create(['group_id' => $group->id, 'challenge_id' => $challenge->id]);
    }






    // -----------------------------------------------------------
    // recordSolve
    // -----------------------------------------------------------
    //
    // Books a solve the way ChallengesController::submit_answer
    // does — the submission row, the history row and the
    // points added to the player — at the given time, now by
    // default. Unlike a real solve it leaves Redis alone: the
    // ranking table is rebuilt by the next request anyway,
    // while the history shown in the sidebar is only written
    // by a real answer.
    //
    // Used by:
    //   - the Feature tests that need a solve on record
    // -----------------------------------------------------------

    protected function recordSolve(User $player, Challenges $challenge, ?string $when = null): void
    {
        $when = $when ?? date('Y-m-d H:i:s');
        ChallengesSubmitted::create([
            'user_id'      => $player->id,
            'challenge_id' => $challenge->id,
            'points_given' => $challenge->points,
            'created'      => $when,
            'ip'           => '127.0.0.1',
        ]);
        ChallengesHistory::create([
            'user_id'      => $player->id,
            'challenge_id' => $challenge->id,
            'created_time' => strtotime($when),
            'created'      => $when,
        ]);
        $player->increment('points', $challenge->points);
    }






    // -----------------------------------------------------------
    // signIn
    // -----------------------------------------------------------
    //
    // Signs the player in on the users guard, which every
    // /user route checks.
    //
    // Used by:
    //   - the Feature tests that need a signed-in player
    // -----------------------------------------------------------

    protected function signIn(User $player): static
    {
        return $this->actingAs($player, 'users');
    }






    // -----------------------------------------------------------
    // signInAdmin
    // -----------------------------------------------------------
    //
    // Signs the admin in on the admins guard and stores its
    // main flag in the session, as the SSO login does. The
    // copy in the session is not what counts: the perms and
    // r_exists middleware and the controllers read the flag
    // from the signed-in account.
    //
    // Used by:
    //   - the Feature tests that need a signed-in admin
    // -----------------------------------------------------------

    protected function signInAdmin(Admin $admin): static
    {
        return $this->actingAs($admin, 'admins')->withSession(['main' => $admin->main]);
    }






    // -----------------------------------------------------------
    // fakeRecaptcha
    // -----------------------------------------------------------
    //
    // Replaces Google's verdict on RECAPTCHA_TOKEN for the
    // given form action with the given score. The forms accept
    // a score of 0.6 and up; a check for any other token or
    // action fails the request, which is how the tests notice
    // a form asking for the wrong action.
    //
    // Used by:
    //   - the registration and password reset tests
    // -----------------------------------------------------------

    protected function fakeRecaptcha(string $action, float $score): void
    {
        RecaptchaV3::shouldReceive('verify')
            ->with(self::RECAPTCHA_TOKEN, $action)
            ->andReturn($score);
    }






    // -----------------------------------------------------------
    // setRegistrationOpen
    // -----------------------------------------------------------
    //
    // Writes settings row 2, which the registration page and
    // the reg.status middleware read: '1' is open, anything
    // else closed.
    //
    // Used by:
    //   - the registration and settings tests
    // -----------------------------------------------------------

    protected function setRegistrationOpen(bool $open): void
    {
        Settings::where('id', 2)->update(['val1' => $open ? '1' : '0']);
    }






    // -----------------------------------------------------------
    // setEventRange
    // -----------------------------------------------------------
    //
    // Writes settings row 1, the time range of the event the
    // lab grew out of. Nothing reads it any more, which the
    // settings tests check by setting a range long past.
    //
    // Used by:
    //   - the settings tests
    // -----------------------------------------------------------

    protected function setEventRange(string $range): void
    {
        Settings::where('id', 1)->update(['val1' => $range]);
    }






    // -----------------------------------------------------------
    // assertErrorFlashed
    // -----------------------------------------------------------
    //
    // Passes when the last response flashed the message into
    // the error bag — the app passes most of its errors as a
    // bare list, which the layouts turn into an error toast,
    // so they have no field name to look them up by.
    //
    // Used by:
    //   - the Feature tests that check refusals
    // -----------------------------------------------------------

    protected function assertErrorFlashed(string $message): void
    {
        $errors = session('errors');
        $this->assertNotNull($errors, "No error was flashed; expected '{$message}'");
        $this->assertContains($message, $errors->all());
    }
}
