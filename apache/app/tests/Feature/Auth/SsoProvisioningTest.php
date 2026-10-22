<?php

// -----------------------------------------------------------
//  [*] SsoProvisioningTest — VU accounts made from SAML data
//
//  After the VU identity provider authenticates someone, the
//  saml_auth middleware hands the SAML attributes to
//  UserAuthController::check_member, which creates the player
//  account on the first visit — the VU user id as username
//  and vu_id, given name and surname as display_name, active
//  at once — and stamps the login on every visit. The admin
//  side, AdminAuthController::check_admin, only recognises
//  admins that already exist and copies their main flag into
//  the session. These tests call the two directly; the
//  middleware around them is SsoLoginTest's.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature\Auth;

use App\Http\Controllers\Admins\AdminAuthController;
use App\Http\Controllers\Users\UserAuthController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;








// -----------------------------------------------------------
// SsoProvisioningTest
// -----------------------------------------------------------
//
//   samlAttributes — the attribute set the VU identity
//                    provider releases
//
// Used by:
//   - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------

final class SsoProvisioningTest extends TestCase
{






    // -----------------------------------------------------------
    // samlAttributes
    // -----------------------------------------------------------
    //
    // The attributes as SimpleSAMLphp hands them over: keyed
    // by OID — uid, mail, givenName, sn — each a list of
    // values of which the app reads the first.
    //
    // Used by:
    //   - the tests below
    // -----------------------------------------------------------

    private static function samlAttributes(string $uid, string $mail, string $given, string $surname): array
    {
        return [
            'urn:oid:0.9.2342.19200300.100.1.1' => [$uid],
            'urn:oid:0.9.2342.19200300.100.1.3' => [$mail],
            'urn:oid:2.5.4.42'                  => [$given],
            'urn:oid:2.5.4.4'                   => [$surname],
        ];
    }






    // -----------------------------------------------------------
    // test_a_first_visit_creates_an_active_account
    // -----------------------------------------------------------
    //
    // An unknown VU user id gets a new account — username and
    // vu_id the user id, the released mail address, the full
    // name in display_name, active, an individual player with
    // no points — and its id is returned for the login.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_first_visit_creates_an_active_account(): void
    {
        $id = UserAuthController::check_member(self::samlAttributes('s2100001', 'ona@sso.test', 'Ona', 'Onaitė'));

        $player = User::findOrFail($id);
        $this->assertSame('s2100001', $player->vu_id);
        $this->assertSame('s2100001', $player->username);
        $this->assertSame('ona@sso.test', $player->email);
        $this->assertSame('Ona Onaitė', $player->display_name);
        $this->assertSame(1, $player->status);
        $this->assertSame(1, $player->user_type);
        $this->assertSame(0, $player->points);
    }






    // -----------------------------------------------------------
    // test_every_visit_stamps_the_login
    // -----------------------------------------------------------
    //
    // The login time and the client address are recorded on
    // the first visit and on every later one.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_every_visit_stamps_the_login(): void
    {
        $player = $this->makeSsoPlayer(['last_login' => '2020-01-01 00:00:00']);
        $_SERVER['REMOTE_ADDR'] = '198.51.100.23';

        UserAuthController::check_member(self::samlAttributes($player->vu_id, $player->email, 'Vardenis', 'Pavardenis'));

        $player->refresh();
        $this->assertSame('198.51.100.23', $player->last_ip);
        $this->assertEqualsWithDelta(time(), strtotime($player->last_login), 60);
    }






    // -----------------------------------------------------------
    // test_a_returning_vu_user_gets_the_same_account
    // -----------------------------------------------------------
    //
    // A known VU user id returns its existing account; no
    // second account is made, and the stored name and email
    // are not refreshed from the SAML data.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_returning_vu_user_gets_the_same_account(): void
    {
        $player = $this->makeSsoPlayer();

        $id = UserAuthController::check_member(self::samlAttributes($player->vu_id, 'new@sso.test', 'Kitas', 'Vardas'));

        $this->assertSame($player->id, $id);
        $this->assertSame(1, User::where('vu_id', $player->vu_id)->count());
        $this->assertSame($player->email, $player->refresh()->email);
        $this->assertSame('Vardenis Pavardenis', $player->display_name);
    }






    // -----------------------------------------------------------
    // test_an_sso_account_cannot_sign_in_through_the_login_form
    // -----------------------------------------------------------
    //
    // The provisioned account gets no password at all, so the
    // local login form can never open it.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_sso_account_cannot_sign_in_through_the_login_form(): void
    {
        $id = UserAuthController::check_member(self::samlAttributes('s2100002', 'jonas@sso.test', 'Jonas', 'Jonaitis'));

        $this->assertSame('', User::findOrFail($id)->password);
        $this->assertFalse(Auth::guard('users')->attempt(['email' => 'jonas@sso.test', 'password' => '']));
        $this->assertFalse(Auth::guard('users')->attempt(['email' => 'jonas@sso.test', 'password' => 'any-password-1']));
    }






    // -----------------------------------------------------------
    // test_a_main_admin_is_recognised_with_its_flag
    // -----------------------------------------------------------
    //
    // An admin's VU user id returns the admin's id and stores
    // its main flag in the session, where the perms and
    // r_exists middleware look for it.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_main_admin_is_recognised_with_its_flag(): void
    {
        $admin = $this->makeAdmin(true);

        $id = AdminAuthController::check_admin(self::samlAttributes($admin->vu_id, $admin->email, 'Main', 'Admin'));

        $this->assertSame($admin->id, $id);
        $this->assertSame(1, session('main'));
    }






    // -----------------------------------------------------------
    // test_a_lab_admin_is_recognised_without_the_flag
    // -----------------------------------------------------------
    //
    // A non-main admin is recognised too, and its session flag
    // says 0.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_lab_admin_is_recognised_without_the_flag(): void
    {
        $admin = $this->makeAdmin();

        $id = AdminAuthController::check_admin(self::samlAttributes($admin->vu_id, $admin->email, 'Lab', 'Admin'));

        $this->assertSame($admin->id, $id);
        $this->assertSame(0, session('main'));
    }






    // -----------------------------------------------------------
    // test_vu_users_are_never_made_admins
    // -----------------------------------------------------------
    //
    // A VU user id with no admin row — a player's included —
    // returns no id, sets no flag and creates nothing: admins
    // are only ever added by hand.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_vu_users_are_never_made_admins(): void
    {
        $player = $this->makeSsoPlayer();

        $id = AdminAuthController::check_admin(self::samlAttributes($player->vu_id, $player->email, 'Vardenis', 'Pavardenis'));

        $this->assertNull($id);
        $this->assertNull(session('main'));
        $this->assertDatabaseMissing('cyber_admins', ['vu_id' => $player->vu_id]);
    }
}
