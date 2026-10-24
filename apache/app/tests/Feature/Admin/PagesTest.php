<?php

// -----------------------------------------------------------
//  [*] PagesTest — writing the content pages
//
//  Main admins write the pages /page/{alias} shows to
//  everyone: a name, free HTML and an alias that must be
//  unique, prefilled with a fresh UUID on a new page. A page
//  that would be incomplete or reuse an alias is not stored;
//  the form comes back with the errors and what was typed.
//  Reading the pages is Play\PageTest's.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature\Admin;

use App\Models\Pages;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;








// -----------------------------------------------------------
// PagesTest
// -----------------------------------------------------------
//
// A main admin is signed in unless a test says otherwise.
//
// Used by:
//   - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------

final class PagesTest extends TestCase
{






    // -----------------------------------------------------------
    // test_the_list_shows_every_page
    // -----------------------------------------------------------
    //
    // Each page is listed by name with edit and delete
    // actions; an empty list says so.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_list_shows_every_page(): void
    {
        $this->signInAdmin($this->makeAdmin(true));
        $this->get('/control-panel/pages')->assertOk()->assertSee('No Pages');

        $page = Pages::create(['name' => 'Rules of Play', 'content' => '<p>Rules</p>', 'alias' => 'rules']);

        $this->get('/control-panel/pages')
            ->assertSee('<td>Rules of Play</td>', false)
            ->assertSee("href=\"/control-panel/pages/edit/{$page->id}\"", false)
            ->assertSee("/control-panel/pages/delete/{$page->id}", false);
    }






    // -----------------------------------------------------------
    // test_the_create_form_offers_a_fresh_alias
    // -----------------------------------------------------------
    //
    // The new-page form posts to the store route with a UUID
    // already in the alias field.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_create_form_offers_a_fresh_alias(): void
    {
        $html = $this->signInAdmin($this->makeAdmin(true))->get('/control-panel/pages/create')->getContent();

        $this->assertStringContainsString('action="https://lab.knf.vu.lt/control-panel/pages/store"', $html);
        $this->assertMatchesRegularExpression('/name="alias" type="text" value="[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}"/', $html);
    }






    // -----------------------------------------------------------
    // test_a_page_is_stored_and_published_under_its_alias
    // -----------------------------------------------------------
    //
    // The stored page keeps its HTML and is readable at
    // /page/{alias} at once.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_page_is_stored_and_published_under_its_alias(): void
    {
        $response = $this->signInAdmin($this->makeAdmin(true))->post('/control-panel/pages/store', [
            'name'    => 'Prize Terms',
            'content' => '<p>Prizes go to the <em>top three</em>.</p>',
            'alias'   => 'prizes',
        ]);

        $response->assertRedirect('/control-panel/pages');
        $response->assertSessionHas('success', 'Created!');
        $this->get('/page/prizes')->assertSee('<em>top three</em>', false);
    }






    // -----------------------------------------------------------
    // test_incomplete_or_clashing_pages_are_not_stored
    // -----------------------------------------------------------
    //
    // A page without a name, content or alias — or with an
    // alias another page has — is not stored, and the form
    // comes back.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    #[DataProvider('refusedPages')]
    public function test_incomplete_or_clashing_pages_are_not_stored(array $fields): void
    {
        Pages::create(['name' => 'Existing', 'content' => '<p>x</p>', 'alias' => 'taken']);

        $response = $this->signInAdmin($this->makeAdmin(true))
            ->from('/control-panel/pages/create')
            ->post('/control-panel/pages/store', array_merge(['name' => 'New', 'content' => '<p>y</p>', 'alias' => 'fresh'], $fields));

        $response->assertRedirect('/control-panel/pages/create');
        $response->assertSessionMissing('success');
        $this->assertSame(1, Pages::count());
    }






    // -----------------------------------------------------------
    // refusedPages
    // -----------------------------------------------------------
    //
    // The fields that replace a valid page's.
    //
    // Used by:
    //   - test_incomplete_or_clashing_pages_are_not_stored
    //     (above)
    // -----------------------------------------------------------

    public static function refusedPages(): array
    {
        return [
            'no name'     => [['name' => '']],
            'no content'  => [['content' => '']],
            'no alias'    => [['alias' => '']],
            'taken alias' => [['alias' => 'taken']],
        ];
    }






    // -----------------------------------------------------------
    // test_a_page_is_edited_and_deleted
    // -----------------------------------------------------------
    //
    // The edit form carries the page and its id; the update
    // rewrites it; deleting removes it from /page too.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_page_is_edited_and_deleted(): void
    {
        $page = Pages::create(['name' => 'Rules of Play', 'content' => '<p>Old rules</p>', 'alias' => 'rules']);
        $this->signInAdmin($this->makeAdmin(true));

        $this->get("/control-panel/pages/edit/{$page->id}")
            ->assertSee('action="https://lab.knf.vu.lt/control-panel/pages/update"', false)
            ->assertSee('value="Rules of Play"', false)
            ->assertSee("name=\"uid\" type=\"hidden\" value=\"{$page->id}\"", false);
        $this->post('/control-panel/pages/update', ['uid' => $page->id, 'name' => 'Rules', 'content' => '<p>New rules</p>', 'alias' => 'rules'])
            ->assertRedirect('/control-panel/pages');
        $this->get('/page/rules')->assertSee('<p>New rules</p>', false);

        $this->from('/control-panel/pages')->post("/control-panel/pages/delete/{$page->id}")->assertSessionHas('success', 'Deleted!');
        $this->get('/page/rules')->assertRedirect('/');
    }






    // -----------------------------------------------------------
    // test_lab_admins_cannot_touch_pages
    // -----------------------------------------------------------
    //
    // Every page route sends a lab admin back with the
    // permissions error, and the page stays.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_admins_cannot_touch_pages(): void
    {
        $page = Pages::create(['name' => 'Rules of Play', 'content' => '<p>Rules</p>', 'alias' => 'rules']);
        $this->signInAdmin($this->makeAdmin())->from('/control-panel/dashboard');

        $this->get("/control-panel/pages/edit/{$page->id}")->assertRedirect('/control-panel/dashboard');
        $this->post('/control-panel/pages/update', ['uid' => $page->id, 'name' => 'X', 'content' => 'X', 'alias' => 'rules'])
            ->assertRedirect('/control-panel/dashboard');
        $this->post('/control-panel/pages/store', ['name' => 'X', 'content' => 'X', 'alias' => 'x'])->assertRedirect('/control-panel/dashboard');
        $this->post("/control-panel/pages/delete/{$page->id}")->assertRedirect('/control-panel/dashboard');

        $this->assertErrorFlashed("You don't have permissions to do this!");
        $this->assertSame('Rules of Play', $page->refresh()->name);
        $this->assertSame(1, Pages::count());
    }






    // -----------------------------------------------------------
    // test_an_incomplete_page_update_is_refused
    // -----------------------------------------------------------
    //
    // A page update missing its content or alias goes back
    // with the errors and what was typed, and the page stays
    // as it was.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_incomplete_page_update_is_refused(): void
    {
        $page = Pages::create(['name' => 'Rules', 'content' => '<p>Rules</p>', 'alias' => 'rules']);

        $response = $this->signInAdmin($this->makeAdmin(true))
            ->from("/control-panel/pages/edit/{$page->id}")
            ->post('/control-panel/pages/update', ['uid' => $page->id, 'name' => 'Half a page']);

        $response->assertRedirect("/control-panel/pages/edit/{$page->id}");
        $response->assertSessionHasErrors(['content', 'alias']);
        $this->assertSame('Half a page', session()->getOldInput('name'));
        $this->assertSame('rules', $page->refresh()->alias);
    }






    // -----------------------------------------------------------
    // test_a_page_cannot_take_another_pages_alias
    // -----------------------------------------------------------
    //
    // An update may keep the page's own alias but not take
    // another page's, which would leave two pages behind one
    // address.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_page_cannot_take_another_pages_alias(): void
    {
        $page = Pages::create(['name' => 'Rules', 'content' => '<p>Rules</p>', 'alias' => 'rules']);
        Pages::create(['name' => 'About', 'content' => '<p>About</p>', 'alias' => 'about']);
        $this->signInAdmin($this->makeAdmin(true))->from("/control-panel/pages/edit/{$page->id}");

        $this->post('/control-panel/pages/update', ['uid' => $page->id, 'name' => 'Rules', 'content' => '<p>New</p>', 'alias' => 'about'])
            ->assertSessionHasErrors('alias');
        $this->assertSame('rules', $page->refresh()->alias);

        $this->post('/control-panel/pages/update', ['uid' => $page->id, 'name' => 'Rules', 'content' => '<p>New</p>', 'alias' => 'rules'])
            ->assertSessionHas('success', 'Updated!');
        $this->assertSame('<p>New</p>', $page->refresh()->content);
    }






    // -----------------------------------------------------------
    // test_a_refused_new_page_says_why
    // -----------------------------------------------------------
    //
    // A new page missing a field, or taking an alias in use,
    // goes back with the errors and what was typed rather than
    // silently.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_refused_new_page_says_why(): void
    {
        Pages::create(['name' => 'About', 'content' => '<p>About</p>', 'alias' => 'about']);

        $response = $this->signInAdmin($this->makeAdmin(true))
            ->from('/control-panel/pages/create')
            ->post('/control-panel/pages/store', ['name' => 'Second About', 'alias' => 'about']);

        $response->assertRedirect('/control-panel/pages/create');
        $response->assertSessionHasErrors(['content', 'alias']);
        $this->assertSame('Second About', session()->getOldInput('name'));
    }






    // -----------------------------------------------------------
    // test_an_unknown_page_is_404
    // -----------------------------------------------------------
    //
    // The edit form of a page that does not exist answers 404
    // instead of a server error.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_unknown_page_is_404(): void
    {
        $this->signInAdmin($this->makeAdmin(true))->get('/control-panel/pages/edit/987654321')->assertNotFound();
    }






    // -----------------------------------------------------------
    // test_a_name_or_alias_longer_than_its_column_is_refused
    // -----------------------------------------------------------
    //
    // An alias holds up to the 50 characters of its column and a
    // name up to 255. A longer one is refused with the error,
    // rather than stored cut short, which would leave the page
    // under an address nobody typed.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_name_or_alias_longer_than_its_column_is_refused(): void
    {
        $page = Pages::create(['name' => 'Kept Name', 'content' => '<p>x</p>', 'alias' => 'kept']);
        $pages = Pages::count();
        $this->signInAdmin($this->makeAdmin(true))->from('/control-panel/pages/create');

        $this->post('/control-panel/pages/store', ['name' => 'Rules', 'content' => '<p>x</p>', 'alias' => str_repeat('a', 51)])->assertSessionHasErrors('alias');
        $this->post('/control-panel/pages/store', ['name' => str_repeat('n', 256), 'content' => '<p>x</p>', 'alias' => 'rules'])->assertSessionHasErrors('name');
        $this->post('/control-panel/pages/update', ['uid' => $page->id, 'name' => 'Kept Name', 'content' => '<p>x</p>', 'alias' => str_repeat('a', 51)])->assertSessionHasErrors('alias');
        $this->assertSame($pages, Pages::count());
        $this->assertSame('kept', $page->refresh()->alias);

        $this->post('/control-panel/pages/store', ['name' => 'Rules', 'content' => '<p>x</p>', 'alias' => str_repeat('a', 50)])->assertSessionHasNoErrors();
        $this->assertSame($pages + 1, Pages::count());
    }






    // -----------------------------------------------------------
    // test_saving_a_deleted_page_says_so
    // -----------------------------------------------------------
    //
    // A page another admin deleted in the meantime is not
    // reported as updated: the form goes back to the pages
    // list with "No page!".
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_saving_a_deleted_page_says_so(): void
    {
        $page = Pages::create(['name' => 'Doomed', 'content' => '<p>x</p>', 'alias' => 'doomed']);
        $page->delete();

        $response = $this->signInAdmin($this->makeAdmin(true))
            ->from("/control-panel/pages/edit/{$page->id}")
            ->post('/control-panel/pages/update', ['uid' => $page->id, 'name' => 'Saved', 'content' => '<p>y</p>', 'alias' => 'saved']);

        $response->assertRedirect('/control-panel/pages');
        $this->assertErrorFlashed('No page!');
        $response->assertSessionMissing('success');
        $this->assertSame(0, Pages::where('alias', 'saved')->count());
    }






    // -----------------------------------------------------------
    // test_a_page_field_in_the_address_never_replaces_the_forms
    // -----------------------------------------------------------
    //
    // An alias put into the edit form's address is passed
    // over: the page keeps the alias of the form, the one the
    // rules checked against the other pages.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_page_field_in_the_address_never_replaces_the_forms(): void
    {
        $page = Pages::create(['name' => 'Rules of Play', 'content' => '<p>Old rules</p>', 'alias' => 'rules']);
        $this->signInAdmin($this->makeAdmin(true));

        $this->post('/control-panel/pages/update?alias=hijacked', ['uid' => $page->id, 'name' => 'Rules', 'content' => '<p>New rules</p>', 'alias' => 'rules']);

        $this->assertSame('rules', $page->refresh()->alias);
    }
}
