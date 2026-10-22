<?php

// -----------------------------------------------------------
//  [*] SettingsTest — the event time range and registration
//
//  Two settings rows drive the player side: row 1 holds the
//  event's time range as "start - end", which every player
//  page reads for its countdown, and row 2 holds whether
//  registration is open ('1') or not. Main admins edit both
//  on the settings page; the registration switch is a
//  checkbox, so an unticked box posts nothing and closes
//  registration.
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
// The settings start as init.sql seeds them: the range
// 2023-02-25 09:00:00 - 2029-02-25 21:00:00, registration
// open.
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
    // The range sits in its text field, and the registration
    // box is ticked while registration is open and unticked
    // once it is closed.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_page_shows_the_current_settings(): void
    {
        $this->signInAdmin($this->makeAdmin(true));

        $this->get('/control-panel/settings')
            ->assertOk()
            ->assertSee('value="2023-02-25 09:00:00 - 2029-02-25 21:00:00"', false)
            ->assertSee('name="reg_open" value="1" checked', false);

        $this->setRegistrationOpen(false);
        $this->get('/control-panel/settings')->assertSee('name="reg_open" value="1" >', false);
    }






    // -----------------------------------------------------------
    // test_the_event_range_is_saved
    // -----------------------------------------------------------
    //
    // The posted range replaces the stored one as written.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_event_range_is_saved(): void
    {
        $response = $this->signInAdmin($this->makeAdmin(true))
            ->from('/control-panel/settings')
            ->post('/control-panel/settings/event_data', ['event_timerange' => '2027-03-01 10:00:00 - 2027-03-02 18:00:00']);

        $response->assertRedirect('/control-panel/settings');
        $response->assertSessionHas('success', 'Updated!');
        $this->assertSame('2027-03-01 10:00:00 - 2027-03-02 18:00:00', Settings::find(1)->val1);
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
    // The page and both forms send a lab admin back with the
    // permissions error, and the settings stay.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_admins_cannot_change_settings(): void
    {
        $this->signInAdmin($this->makeAdmin())->from('/control-panel/dashboard');

        $this->get('/control-panel/settings')->assertRedirect('/control-panel/dashboard');
        $this->post('/control-panel/settings/event_data', ['event_timerange' => 'x - y'])->assertRedirect('/control-panel/dashboard');
        $this->post('/control-panel/settings/reg_open', [])->assertRedirect('/control-panel/dashboard');

        $this->assertErrorFlashed("You don't have permissions to do this!");
        $this->assertSame('2023-02-25 09:00:00 - 2029-02-25 21:00:00', Settings::find(1)->val1);
        $this->assertSame('1', Settings::find(2)->val1);
    }
}
