<?php

// -----------------------------------------------------------
//  [*] SettingsTest — the registration switch
//
//  Settings row 2 holds whether registration is open ('1')
//  or not, and main admins switch it on the settings page.
//  The switch is a checkbox, so an unticked box posts
//  nothing and closes registration. Row 1 still holds the
//  time range of the event the lab grew out of, but nothing
//  reads it any more: the lab is open at any time.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature\Admin;

use App\Models\Settings;
use Tests\TestCase;








// -----------------------------------------------------------
// SettingsTest
// -----------------------------------------------------------
//
// The settings start as init.sql seeds them, with
// registration open.
//
// Used by:
//   - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------

final class SettingsTest extends TestCase
{






    // -----------------------------------------------------------
    // test_the_page_shows_the_current_settings
    // -----------------------------------------------------------
    //
    // The registration box is ticked while registration is
    // open and unticked once it is closed, and the event
    // range has no field any more.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_page_shows_the_current_settings(): void
    {
        $this->signInAdmin($this->makeAdmin(true));

        $this->get('/control-panel/settings')
            ->assertOk()
            ->assertSee('name="reg_open" value="1" checked', false)
            ->assertDontSee('event_timerange');

        $this->setRegistrationOpen(false);
        $this->get('/control-panel/settings')->assertSee('name="reg_open" value="1" >', false);
    }






    // -----------------------------------------------------------
    // test_the_registration_switch_opens_and_closes_registration
    // -----------------------------------------------------------
    //
    // Posting the ticked box opens registration; posting the
    // form with the box unticked closes it, and the player
    // side follows at once.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_registration_switch_opens_and_closes_registration(): void
    {
        $this->signInAdmin($this->makeAdmin(true))->from('/control-panel/settings');

        $this->post('/control-panel/settings/reg_open', [])->assertSessionHas('success', 'Updated!');
        $this->assertNull(Settings::find(2)->val1);
        $this->app['auth']->guard('admins')->logout();
        $this->get('/register')->assertSee('Registration is closed!');

        $this->signInAdmin($this->makeAdmin(true))->post('/control-panel/settings/reg_open', ['reg_open' => '1']);
        $this->assertSame('1', Settings::find(2)->val1);
        $this->app['auth']->guard('admins')->logout();
        $this->get('/register')->assertDontSee('Registration is closed!');
    }






    // -----------------------------------------------------------
    // test_lab_admins_cannot_change_settings
    // -----------------------------------------------------------
    //
    // The page and its form send a lab admin back with the
    // permissions error, and registration stays open.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_admins_cannot_change_settings(): void
    {
        $this->signInAdmin($this->makeAdmin())->from('/control-panel/dashboard');

        $this->get('/control-panel/settings')->assertRedirect('/control-panel/dashboard');
        $this->post('/control-panel/settings/reg_open', [])->assertRedirect('/control-panel/dashboard');

        $this->assertErrorFlashed("You don't have permissions to do this!");
        $this->assertSame('1', Settings::find(2)->val1);
    }






    // -----------------------------------------------------------
    // test_an_old_event_range_closes_nothing
    // -----------------------------------------------------------
    //
    // A row 1 range that ended long ago keeps no one out: the
    // login page answers without a countdown, a player signs
    // in and the player pages open.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_old_event_range_closes_nothing(): void
    {
        $this->setEventRange('2020-01-01 09:00:00 - 2020-01-02 21:00:00');
        $player = $this->makePlayer();

        $this->get('/')->assertOk()->assertDontSee('Event is starting in');
        $this->post('/login', ['email' => $player->email, 'password' => self::PASSWORD])->assertRedirect('/user/dashboard');
        $this->get('/user/leaderboard')->assertOk();
    }






    // -----------------------------------------------------------
    // test_a_setting_in_the_address_never_replaces_the_forms
    // -----------------------------------------------------------
    //
    // A value put into the switch's address is passed over:
    // registration takes the value the form sends.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_setting_in_the_address_never_replaces_the_forms(): void
    {
        $this->signInAdmin($this->makeAdmin(true))->post('/control-panel/settings/reg_open?reg_open=1', ['reg_open' => '0']);

        $this->assertSame('0', (string) Settings::find(2)->val1);
    }






    // -----------------------------------------------------------
    // test_the_registration_switch_takes_its_checkbox_only
    // -----------------------------------------------------------
    //
    // The registration switch takes what its checkbox sends —
    // 1, or nothing — or a 0, and refuses any other value, a
    // list among them, with the error, leaving the setting as
    // it was.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_registration_switch_takes_its_checkbox_only(): void
    {
        $this->setRegistrationOpen(true);
        $this->signInAdmin($this->makeAdmin(true))->from('/control-panel/settings');

        $this->post('/control-panel/settings/reg_open', ['reg_open' => ['1']])->assertSessionHasErrors('reg_open');
        $this->post('/control-panel/settings/reg_open', ['reg_open' => 'yes'])->assertSessionHasErrors('reg_open');

        $this->assertSame('1', Settings::find(2)->val1);
    }
}
