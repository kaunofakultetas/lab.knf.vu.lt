<?php

// -----------------------------------------------------------
//  [*] EmailSyncTest — syncing the Mailjet contact lists
//
//  The two sync buttons on the mail page put players on the
//  Mailjet contact lists the mail templates are sent to:
//  every active account on the "registered" list (10226591),
//  every newsletter subscriber on the "newsletters" list
//  (10226593). For each address the app asks Mailjet whether
//  the contact exists, creates it when it does not, and adds
//  it to the list; malformed addresses are skipped before
//  Mailjet is asked.
//
//  The app builds its own Mailjet client for every address,
//  and the tests container cannot reach Mailjet. So each
//  test replaces the client class with a Mockery overload
//  that answers from a list of known contacts and records
//  every call, and the controller runs for real around it.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature\Admin;

use Mailjet\Resources;
use Mockery;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;








// -----------------------------------------------------------
// EmailSyncTest
// -----------------------------------------------------------
//
// An overload can only replace a class PHP has not loaded
// yet, so every test runs in a PHP process of its own.
//
//   fakeMailjet — overloads the Mailjet client
//
// Used by:
//   - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class EmailSyncTest extends TestCase
{

    // Every call the fake Mailjet client received, as the
    // method name followed by its arguments
    private array $calls = [];






    // -----------------------------------------------------------
    // fakeMailjet
    // -----------------------------------------------------------
    //
    // Replaces Mailjet\Client for the rest of the test. A
    // contact lookup answers with the contact's id when the
    // address is among the known contacts given, and with
    // nothing otherwise; creating a contact answers with id
    // 900; every call is recorded in calls.
    //
    // Used by:
    //   - the tests below
    // -----------------------------------------------------------

    private function fakeMailjet(array $knownContacts): void
    {
        $client = Mockery::mock('overload:Mailjet\Client');
        $client->shouldReceive('get')->andReturnUsing(function ($resource, $args) use ($knownContacts) {
            $this->calls[] = ['get', $resource, $args];
            $email = $args['filters']['Email'];
            return Mockery::mock(['getData' => isset($knownContacts[$email]) ? [['ID' => $knownContacts[$email]]] : []]);
        });
        $client->shouldReceive('post')->andReturnUsing(function ($resource, $args) {
            $this->calls[] = ['post', $resource, $args];
            return Mockery::mock(['getData' => [['ID' => 900]]]);
        });
    }






    // -----------------------------------------------------------
    // test_a_known_contact_is_put_on_the_registered_list
    // -----------------------------------------------------------
    //
    // An active account whose address Mailjet knows is looked
    // up and added to the registered list as subscribed — and
    // not created again.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_known_contact_is_put_on_the_registered_list(): void
    {
        $this->makePlayer(['email' => 'ona@players.test']);
        $this->fakeMailjet(['ona@players.test' => 77]);

        $response = $this->signInAdmin($this->makeAdmin(true))
            ->from('/control-panel/emails')
            ->post('/control-panel/emails/sync/registered');

        $response->assertRedirect('/control-panel/emails');
        $response->assertSessionHas('success', 'registered email list synced!');
        $this->assertEquals([
            ['get', Resources::$Contact, ['filters' => ['Email' => 'ona@players.test']]],
            ['post', Resources::$Listrecipient, ['body' => ['IsUnsubscribed' => 'false', 'ContactID' => 77, 'ListID' => 10226591]]],
        ], $this->calls);
    }






    // -----------------------------------------------------------
    // test_an_unknown_contact_is_created_first
    // -----------------------------------------------------------
    //
    // An address Mailjet does not know is created as a contact
    // that may receive campaigns, and the new contact is added
    // to the list.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_unknown_contact_is_created_first(): void
    {
        $this->makePlayer(['email' => 'jonas@players.test']);
        $this->fakeMailjet([]);

        $this->signInAdmin($this->makeAdmin(true))->post('/control-panel/emails/sync/registered');

        $this->assertEquals([
            ['get', Resources::$Contact, ['filters' => ['Email' => 'jonas@players.test']]],
            ['post', Resources::$Contact, ['body' => ['IsExcludedFromCampaigns' => 'false', 'Email' => 'jonas@players.test', 'IsOptInPending' => 'false']]],
            ['post', Resources::$Listrecipient, ['body' => ['IsUnsubscribed' => 'false', 'ContactID' => 900, 'ListID' => 10226591]]],
        ], $this->calls);
    }






    // -----------------------------------------------------------
    // test_only_active_accounts_join_the_registered_list
    // -----------------------------------------------------------
    //
    // Inactive accounts are left out of the registered list.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_only_active_accounts_join_the_registered_list(): void
    {
        $this->makePlayer(['email' => 'active@players.test']);
        $this->makePlayer(['email' => 'inactive@players.test', 'status' => 0]);
        $this->fakeMailjet(['active@players.test' => 1, 'inactive@players.test' => 2]);

        $this->signInAdmin($this->makeAdmin(true))->post('/control-panel/emails/sync/registered');

        $lookups = array_filter($this->calls, fn (array $call) => $call[0] === 'get');
        $this->assertSame(['active@players.test'], array_map(fn (array $call) => $call[2]['filters']['Email'], array_values($lookups)));
    }






    // -----------------------------------------------------------
    // test_the_newsletter_list_takes_subscribers_only
    // -----------------------------------------------------------
    //
    // The newsletter sync adds the accounts that asked for the
    // newsletter — active or not — to list 10226593, and no
    // one else.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_newsletter_list_takes_subscribers_only(): void
    {
        $this->makePlayer(['email' => 'reader@players.test', 'newsletter' => 1, 'status' => 0]);
        $this->makePlayer(['email' => 'quiet@players.test', 'newsletter' => 0]);
        $this->fakeMailjet(['reader@players.test' => 5, 'quiet@players.test' => 6]);

        $response = $this->signInAdmin($this->makeAdmin(true))->post('/control-panel/emails/sync/newsletters');

        $response->assertSessionHas('success', 'newsletters email list synced!');
        $this->assertEquals([
            ['get', Resources::$Contact, ['filters' => ['Email' => 'reader@players.test']]],
            ['post', Resources::$Listrecipient, ['body' => ['IsUnsubscribed' => 'false', 'ContactID' => 5, 'ListID' => 10226593]]],
        ], $this->calls);
    }






    // -----------------------------------------------------------
    // test_malformed_addresses_never_reach_mailjet
    // -----------------------------------------------------------
    //
    // Accounts admins created before the account dialog
    // checked addresses can carry ones that are not
    // addresses; they are skipped without a word to Mailjet,
    // and the rest are synced.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_malformed_addresses_never_reach_mailjet(): void
    {
        $this->makePlayer(['email' => 'not-an-address']);
        $this->makePlayer(['email' => 'fine@players.test']);
        $this->fakeMailjet(['fine@players.test' => 3]);

        $this->signInAdmin($this->makeAdmin(true))->post('/control-panel/emails/sync/registered');

        $this->assertCount(2, $this->calls);
        $this->assertSame('fine@players.test', $this->calls[0][2]['filters']['Email']);
    }
}
