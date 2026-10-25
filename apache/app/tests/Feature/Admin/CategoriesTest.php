<?php

// -----------------------------------------------------------
//  [*] CategoriesTest — the challenge categories
//
//  Main admins keep the list of categories the dashboard
//  filters by: the seven init.sql seeds and whatever is added
//  later. New and renamed categories go through small dialogs
//  loaded into a modal; deleting a category also takes it off
//  every challenge. The list, the dialogs and the deletion
//  are all main-only.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature\Admin;

use App\Models\Categories;
use App\Models\ChallengesCategories;
use Tests\TestCase;








// -----------------------------------------------------------
// CategoriesTest
// -----------------------------------------------------------
//
// A main admin is signed in unless a test says otherwise.
//
// Used by:
//   - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------

final class CategoriesTest extends TestCase
{

    // The refusal the perms middleware flashes
    private const REFUSAL = "You don't have permissions to do this!";






    // -----------------------------------------------------------
    // test_the_list_shows_every_category
    // -----------------------------------------------------------
    //
    // The seeded categories and new ones are all listed, each
    // with its edit and delete actions.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_list_shows_every_category(): void
    {
        $category = $this->makeCategory('Hardware Hacking');

        $response = $this->signInAdmin($this->makeAdmin(true))->get('/control-panel/categories');

        $response->assertOk();
        $response->assertViewIs('admins.categories');
        $response->assertSee('<td>Cryptography</td>', false);
        $response->assertSee('<td>Reverse Engineering</td>', false);
        $response->assertSee('<td>Hardware Hacking</td>', false);
        $response->assertSee("category_form('edit', {$category->id})", false);
        $response->assertSee("/control-panel/category/delete/{$category->id}", false);
    }






    // -----------------------------------------------------------
    // test_the_new_category_dialog_posts_to_the_store_route
    // -----------------------------------------------------------
    //
    // The dialog is a bare form posting a name.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_new_category_dialog_posts_to_the_store_route(): void
    {
        $response = $this->signInAdmin($this->makeAdmin(true))->get('/control-panel/dialog/category/new');

        $response->assertOk();
        $response->assertSee('action="https://lab.knf.vu.lt/control-panel/dialog/category/store"', false);
        $response->assertSee('name="name"', false);
    }






    // -----------------------------------------------------------
    // test_a_category_is_created
    // -----------------------------------------------------------
    //
    // The posted name becomes a category, and the admin
    // returns to the list.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_category_is_created(): void
    {
        $response = $this->signInAdmin($this->makeAdmin(true))
            ->from('/control-panel/categories')
            ->post('/control-panel/dialog/category/store', ['name' => 'Hardware Hacking']);

        $response->assertRedirect('/control-panel/categories');
        $response->assertSessionHas('success', 'Created!');
        $this->assertDatabaseHas('cyber_categories', ['name' => 'Hardware Hacking']);
    }






    // -----------------------------------------------------------
    // test_the_edit_dialog_is_filled_with_the_category
    // -----------------------------------------------------------
    //
    // The edit dialog carries the current name and posts to
    // the update route with the category's id.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_edit_dialog_is_filled_with_the_category(): void
    {
        $category = $this->makeCategory('Hardware Hacking');

        $response = $this->signInAdmin($this->makeAdmin(true))->get("/control-panel/dialog/category/edit/{$category->id}");

        $response->assertSee('action="https://lab.knf.vu.lt/control-panel/dialog/category/update"', false);
        $response->assertSee('value="Hardware Hacking"', false);
        $response->assertSee("name=\"uid\" type=\"hidden\" value=\"{$category->id}\"", false);
    }






    // -----------------------------------------------------------
    // test_a_category_is_renamed
    // -----------------------------------------------------------
    //
    // The update stores the new name.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_category_is_renamed(): void
    {
        $category = $this->makeCategory('Hardware Hacking');

        $response = $this->signInAdmin($this->makeAdmin(true))
            ->from('/control-panel/categories')
            ->post('/control-panel/dialog/category/update', ['uid' => $category->id, 'name' => 'Hardware Security']);

        $response->assertSessionHas('success', 'Updated!');
        $this->assertSame('Hardware Security', $category->refresh()->name);
    }






    // -----------------------------------------------------------
    // test_deleting_a_category_takes_it_off_every_challenge
    // -----------------------------------------------------------
    //
    // The category goes, and so do its links to challenges;
    // the challenges themselves and their other categories
    // stay.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_deleting_a_category_takes_it_off_every_challenge(): void
    {
        $category = $this->makeCategory('Hardware Hacking');
        $challenge = $this->makeChallenge();
        ChallengesCategories::create(['challenge_id' => $challenge->id, 'cat_id' => $category->id]);
        ChallengesCategories::create(['challenge_id' => $challenge->id, 'cat_id' => 1]);

        $response = $this->signInAdmin($this->makeAdmin(true))
            ->from('/control-panel/categories')
            ->post("/control-panel/category/delete/{$category->id}");

        $response->assertRedirect('/control-panel/categories');
        $response->assertSessionHas('success', 'Deleted!');
        $this->assertNull(Categories::find($category->id));
        $this->assertSame([1], ChallengesCategories::where('challenge_id', $challenge->id)->pluck('cat_id')->all());
        $this->assertNotNull($challenge->fresh());
    }






    // -----------------------------------------------------------
    // test_lab_admins_cannot_list_or_delete_categories
    // -----------------------------------------------------------
    //
    // The list and the deletion send a lab admin back with
    // the permissions error, and the category stays.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_admins_cannot_list_or_delete_categories(): void
    {
        $category = $this->makeCategory('Hardware Hacking');
        $this->signInAdmin($this->makeAdmin())->from('/control-panel/dashboard');

        $this->get('/control-panel/categories')->assertRedirect('/control-panel/dashboard');
        $this->assertErrorFlashed(self::REFUSAL);
        $this->post("/control-panel/category/delete/{$category->id}")->assertRedirect('/control-panel/dashboard');
        $this->assertErrorFlashed(self::REFUSAL);

        $this->assertNotNull($category->fresh());
    }






    // -----------------------------------------------------------
    // test_lab_admins_cannot_create_or_rename_categories
    // -----------------------------------------------------------
    //
    // The category dialog is main-only like the categories page:
    // a lab admin who posts it anyway is refused, and nothing is
    // added or renamed.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_admins_cannot_create_or_rename_categories(): void
    {
        $this->signInAdmin($this->makeAdmin())->from('/control-panel/dashboard');

        $this->post('/control-panel/dialog/category/store', ['name' => 'Lab Admin Category']);
        $created = session('errors') ? session('errors')->all() : [];
        $this->post('/control-panel/dialog/category/update', ['uid' => 1, 'name' => 'Renamed By A Lab Admin']);
        $renamed = session('errors') ? session('errors')->all() : [];

        $this->assertContains(self::REFUSAL, $created);
        $this->assertContains(self::REFUSAL, $renamed);
        $this->assertSame(0, Categories::where('name', 'Lab Admin Category')->count());
        $this->assertSame('Cryptography', Categories::find(1)->name);
    }






    // -----------------------------------------------------------
    // test_an_empty_category_form_is_refused
    // -----------------------------------------------------------
    //
    // The new-category dialog sent without a name goes back with
    // the error and adds nothing.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_empty_category_form_is_refused(): void
    {
        $before = Categories::count();

        $response = $this->signInAdmin($this->makeAdmin(true))->from('/control-panel/categories')->post('/control-panel/dialog/category/store', []);

        $response->assertRedirect('/control-panel/categories');
        $response->assertSessionHasErrors('name');
        $this->assertSame($before, Categories::count());
    }






    // -----------------------------------------------------------
    // test_an_empty_category_rename_is_refused
    // -----------------------------------------------------------
    //
    // The rename dialog sent empty goes back with the error,
    // and the category keeps its name.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_empty_category_rename_is_refused(): void
    {
        $category = $this->makeCategory('Kept Category');

        $response = $this->signInAdmin($this->makeAdmin(true))
            ->from('/control-panel/categories')
            ->post('/control-panel/dialog/category/update', ['uid' => $category->id, 'name' => '']);

        $response->assertRedirect('/control-panel/categories');
        $response->assertSessionHasErrors('name');
        $this->assertSame('Kept Category', $category->refresh()->name);
    }






    // -----------------------------------------------------------
    // test_an_unknown_category_is_404
    // -----------------------------------------------------------
    //
    // The rename dialog of a category that does not exist
    // answers 404 instead of a server error.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_unknown_category_is_404(): void
    {
        $this->signInAdmin($this->makeAdmin(true))->get('/control-panel/dialog/category/edit/987654321')->assertNotFound();
    }






    // -----------------------------------------------------------
    // test_a_name_longer_than_its_column_is_refused
    // -----------------------------------------------------------
    //
    // A category name holds up to the 255 characters of its
    // column; a longer one is refused with the error rather than
    // stored cut short.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_name_longer_than_its_column_is_refused(): void
    {
        $category = $this->makeCategory('Kept Name');
        $categories = Categories::count();
        $this->signInAdmin($this->makeAdmin(true))->from('/control-panel/categories');

        $this->post('/control-panel/dialog/category/store', ['name' => str_repeat('n', 256)])->assertSessionHasErrors('name');
        $this->post('/control-panel/dialog/category/update', ['uid' => $category->id, 'name' => str_repeat('n', 256)])->assertSessionHasErrors('name');

        $this->assertSame($categories, Categories::count());
        $this->assertSame('Kept Name', $category->refresh()->name);
    }






    // -----------------------------------------------------------
    // test_renaming_a_deleted_category_says_so
    // -----------------------------------------------------------
    //
    // A category another admin deleted in the meantime is not
    // reported as renamed: the dialog's form goes back with
    // "No category!".
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_renaming_a_deleted_category_says_so(): void
    {
        $category = $this->makeCategory('Doomed');
        $category->delete();

        $response = $this->signInAdmin($this->makeAdmin(true))
            ->from('/control-panel/categories')
            ->post('/control-panel/dialog/category/update', ['uid' => $category->id, 'name' => 'Renamed']);

        $response->assertRedirect('/control-panel/categories');
        $this->assertErrorFlashed('No category!');
        $response->assertSessionMissing('success');
    }






    // -----------------------------------------------------------
    // test_a_category_name_in_the_address_never_replaces_the_forms
    // -----------------------------------------------------------
    //
    // A name put into the form's address, however long, is
    // passed over: the category is stored with the name of the
    // form, the one the rules checked.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_category_name_in_the_address_never_replaces_the_forms(): void
    {
        $this->signInAdmin($this->makeAdmin(true))
            ->from('/control-panel/categories')
            ->post('/control-panel/dialog/category/store?name=' . str_repeat('n', 300), ['name' => 'Hardware Hacking']);

        $this->assertTrue(Categories::where('name', 'Hardware Hacking')->exists());
    }






    // -----------------------------------------------------------
    // test_a_list_for_a_field_is_refused
    // -----------------------------------------------------------
    //
    // A category form with a list for the name, or for the uid
    // — only a hand-made request sends one — goes back with
    // the error and changes nothing, rather than ending the
    // save on a 500.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_list_for_a_field_is_refused(): void
    {
        $category = $this->makeCategory('Kept Name');
        $this->signInAdmin($this->makeAdmin(true))->from('/control-panel/categories');

        $this->post('/control-panel/dialog/category/store', ['name' => ['Listed']])->assertRedirect('/control-panel/categories')->assertSessionHasErrors('name');
        $this->post('/control-panel/dialog/category/update', ['uid' => [$category->id], 'name' => 'Listed'])->assertRedirect('/control-panel/categories')->assertSessionHasErrors('uid');

        $this->assertSame('Kept Name', $category->refresh()->name);
    }
}
