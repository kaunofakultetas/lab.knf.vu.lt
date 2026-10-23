<?php

// -----------------------------------------------------------
//  [*] EmailsTest — mail templates and their sending
//
//  Main admins write HTML mail templates, try one out on an
//  address of their choice, and send it once to the
//  registered players through the Mailjet list address
//  configured as MAILJET_REGISTERED_LIST — after which it is
//  marked sent and loses its send action. The lists
//  themselves are synced with Mailjet from the same page;
//  syncing with nobody to add is covered here, the Mailjet
//  conversation in EmailSyncTest.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature\Admin;

use App\Mail\SendMail;
use App\Models\Emails;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;








// -----------------------------------------------------------
// EmailsTest
// -----------------------------------------------------------
//
// Mail is faked in every test, and a main admin is signed in
// unless a test says otherwise.
//
//   makeTemplate — a stored mail template
//
// Used by:
//   - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------

final class EmailsTest extends TestCase
{






    // -----------------------------------------------------------
    // setUp
    // -----------------------------------------------------------
    //
    // Fakes the mailer.
    //
    // Used by:
    //   - PHPUnit, before every test
    // -----------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }






    // -----------------------------------------------------------
    // tearDown
    // -----------------------------------------------------------
    //
    // Forgets the list address a test may have configured.
    //
    // Used by:
    //   - PHPUnit, after every test
    // -----------------------------------------------------------

    protected function tearDown(): void
    {
        unset($_SERVER['MAILJET_REGISTERED_LIST']);
        parent::tearDown();
    }






    // -----------------------------------------------------------
    // makeTemplate
    // -----------------------------------------------------------
    //
    // A stored template, unsent unless the attributes say
    // otherwise.
    //
    // Used by:
    //   - the tests below
    // -----------------------------------------------------------

    private function makeTemplate(array $attributes = []): Emails
    {
        return Emails::create(array_merge([
            'name'    => 'Spring reminder',
            'subject' => 'The spring challenges are open',
            'content' => '<p>New <b>challenges</b> are waiting.</p>',
            'sent'    => 0,
        ], $attributes));
    }






    // -----------------------------------------------------------
    // test_the_list_shows_templates_with_their_actions
    // -----------------------------------------------------------
    //
    // Each template shows its name, subject, a test action,
    // edit and delete — and a send action while unsent. The
    // page also offers the two list syncs.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_list_shows_templates_with_their_actions(): void
    {
        $unsent = $this->makeTemplate(['name' => 'Unsent One']);
        $sent = $this->makeTemplate(['name' => 'Sent One', 'sent' => 1]);

        $response = $this->signInAdmin($this->makeAdmin(true))->get('/control-panel/emails');

        $response->assertOk();
        $response->assertSee('<td>Unsent One</td>', false);
        $response->assertSee('<td>The spring challenges are open</td>', false);
        $response->assertSee("email_test({$unsent->id})", false);
        $response->assertSee("/control-panel/emails/send/{$unsent->id}", false);
        $response->assertDontSee("/control-panel/emails/send/{$sent->id}", false);
        $response->assertSee("href=\"/control-panel/emails/edit/{$sent->id}\"", false);
        $response->assertSee("onClick=\"Post_Link('/control-panel/emails/sync/registered');\"", false);
        $response->assertSee("onClick=\"Post_Link('/control-panel/emails/sync/newsletters');\"", false);
    }






    // -----------------------------------------------------------
    // test_an_empty_list_says_so
    // -----------------------------------------------------------
    //
    // Without templates the list says there are none.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_empty_list_says_so(): void
    {
        $this->signInAdmin($this->makeAdmin(true))->get('/control-panel/emails')->assertSee('No emails');
    }






    // -----------------------------------------------------------
    // test_a_template_is_written_and_starts_unsent
    // -----------------------------------------------------------
    //
    // The create form posts to the store route; the stored
    // template keeps its HTML and is not marked sent.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_template_is_written_and_starts_unsent(): void
    {
        $this->signInAdmin($this->makeAdmin(true));
        $this->get('/control-panel/emails/create')
            ->assertSee('action="https://lab.knf.vu.lt/control-panel/emails/store"', false);

        $response = $this->post('/control-panel/emails/store', [
            'name'    => 'Spring reminder',
            'subject' => 'The spring challenges are open',
            'content' => '<p>New <b>challenges</b> are waiting.</p>',
        ]);

        $response->assertRedirect('/control-panel/emails');
        $response->assertSessionHas('success', 'Created!');
        $template = Emails::where('name', 'Spring reminder')->firstOrFail();
        $this->assertSame('<p>New <b>challenges</b> are waiting.</p>', $template->content);
        $this->assertSame(0, (int) $template->sent);
    }






    // -----------------------------------------------------------
    // test_a_template_is_edited
    // -----------------------------------------------------------
    //
    // The edit form carries the template and its id; the
    // update stores the new values.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_template_is_edited(): void
    {
        $template = $this->makeTemplate();
        $this->signInAdmin($this->makeAdmin(true));

        $this->get("/control-panel/emails/edit/{$template->id}")
            ->assertSee('action="https://lab.knf.vu.lt/control-panel/emails/update"', false)
            ->assertSee('value="The spring challenges are open"', false)
            ->assertSee("name=\"uid\" type=\"hidden\" value=\"{$template->id}\"", false);
        $this->post('/control-panel/emails/update', [
            'uid'     => $template->id,
            'name'    => 'Autumn reminder',
            'subject' => 'Autumn',
            'content' => '<p>Autumn.</p>',
        ])->assertRedirect('/control-panel/emails');

        $template->refresh();
        $this->assertSame('Autumn reminder', $template->name);
        $this->assertSame('Autumn', $template->subject);
        $this->assertSame('<p>Autumn.</p>', $template->content);
    }






    // -----------------------------------------------------------
    // test_a_template_is_deleted
    // -----------------------------------------------------------
    //
    // Deleting removes the template.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_template_is_deleted(): void
    {
        $template = $this->makeTemplate();

        $this->signInAdmin($this->makeAdmin(true))
            ->from('/control-panel/emails')
            ->post("/control-panel/emails/delete/{$template->id}")
            ->assertSessionHas('success', 'Deleted!');

        $this->assertNull(Emails::find($template->id));
    }






    // -----------------------------------------------------------
    // test_a_test_mail_goes_to_the_given_address_only
    // -----------------------------------------------------------
    //
    // The test dialog asks for an address; the template goes
    // there with its subject and HTML, and stays unsent.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_test_mail_goes_to_the_given_address_only(): void
    {
        $template = $this->makeTemplate();
        $this->signInAdmin($this->makeAdmin(true));

        $this->get("/control-panel/dialog/emails/send_test/{$template->id}")
            ->assertSee("action=\"https://lab.knf.vu.lt/control-panel/emails/sending_test/{$template->id}\"", false);
        $response = $this->post("/control-panel/emails/sending_test/{$template->id}", ['email' => 'me@admins.test']);

        $response->assertRedirect('/control-panel/emails');
        $response->assertSessionHas('success', 'Test email sent!');
        Mail::assertSent(SendMail::class, function (SendMail $mail) {
            return $mail->hasTo('me@admins.test')
                && $mail->subject === 'The spring challenges are open'
                && $mail->content === '<p>New <b>challenges</b> are waiting.</p>';
        });
        Mail::assertSent(SendMail::class, 1);
        $this->assertSame(0, (int) $template->refresh()->sent);
    }






    // -----------------------------------------------------------
    // test_sending_mails_the_registered_list_once
    // -----------------------------------------------------------
    //
    // Sending mails the template to the configured Mailjet
    // list address and marks it sent, which takes its send
    // action off the list.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_sending_mails_the_registered_list_once(): void
    {
        $_SERVER['MAILJET_REGISTERED_LIST'] = 'registered@lists.test';
        $template = $this->makeTemplate();
        $this->signInAdmin($this->makeAdmin(true));

        $response = $this->post("/control-panel/emails/send/{$template->id}");

        $response->assertRedirect('/control-panel/emails');
        $response->assertSessionHas('success', 'Email sent!');
        Mail::assertSent(SendMail::class, function (SendMail $mail) {
            return $mail->hasTo('registered@lists.test') && $mail->subject === 'The spring challenges are open';
        });
        $this->assertSame(1, (int) $template->refresh()->sent);
        $this->get('/control-panel/emails')->assertDontSee("/control-panel/emails/send/{$template->id}", false);
    }






    // -----------------------------------------------------------
    // test_syncing_with_nobody_to_add_asks_nothing_of_mailjet
    // -----------------------------------------------------------
    //
    // With no active account and no newsletter subscriber both
    // syncs report success at once — Mailjet is never asked,
    // which the missing network would otherwise reveal.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_syncing_with_nobody_to_add_asks_nothing_of_mailjet(): void
    {
        $this->makePlayer(['status' => 0, 'newsletter' => 0]);
        $this->signInAdmin($this->makeAdmin(true))->from('/control-panel/emails');

        $this->post('/control-panel/emails/sync/registered')
            ->assertRedirect('/control-panel/emails')
            ->assertSessionHas('success', 'registered email list synced!');
        $this->post('/control-panel/emails/sync/newsletters')
            ->assertSessionHas('success', 'newsletters email list synced!');
    }






    // -----------------------------------------------------------
    // test_lab_admins_cannot_touch_emails
    // -----------------------------------------------------------
    //
    // Every mail route sends a lab admin back with the
    // permissions error; nothing is mailed or changed.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_admins_cannot_touch_emails(): void
    {
        $template = $this->makeTemplate();
        $this->signInAdmin($this->makeAdmin())->from('/control-panel/dashboard');

        foreach ([
            "/control-panel/emails/edit/{$template->id}",
            "/control-panel/dialog/emails/send_test/{$template->id}",
        ] as $page) {
            $this->get($page)->assertRedirect('/control-panel/dashboard');
        }
        foreach ([
            "/control-panel/emails/send/{$template->id}",
            "/control-panel/emails/delete/{$template->id}",
            '/control-panel/emails/sync/registered',
        ] as $action) {
            $this->post($action)->assertRedirect('/control-panel/dashboard');
        }
        $this->post('/control-panel/emails/store', ['name' => 'x', 'subject' => 'x', 'content' => 'x'])->assertRedirect('/control-panel/dashboard');
        $this->post("/control-panel/emails/sending_test/{$template->id}", ['email' => 'me@admins.test'])->assertRedirect('/control-panel/dashboard');

        $this->assertErrorFlashed("You don't have permissions to do this!");
        Mail::assertNothingSent();
        $this->assertSame(1, Emails::count());
        $this->assertSame(0, (int) $template->refresh()->sent);
    }






    // -----------------------------------------------------------
    // test_an_empty_template_form_is_refused
    // -----------------------------------------------------------
    //
    // A template form sent empty goes back with an error for the
    // name, subject and content, and stores nothing.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_empty_template_form_is_refused(): void
    {
        $before = Emails::count();

        $response = $this->signInAdmin($this->makeAdmin(true))->from('/control-panel/emails/create')->post('/control-panel/emails/store', []);

        $response->assertRedirect('/control-panel/emails/create');
        $response->assertSessionHasErrors(['name', 'subject', 'content']);
        $this->assertSame($before, Emails::count());
    }
}
