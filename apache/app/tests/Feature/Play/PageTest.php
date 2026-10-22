<?php

// -----------------------------------------------------------
//  [*] PageTest — the content pages under /page/{alias}
//
//  Main admins write pages of free HTML (rules, about, prize
//  terms) in the control panel; /page/{alias} shows them to
//  anyone, signed in or not. The content is printed as
//  written, markup included — it comes from admins only. An
//  alias that names no page sends the visitor to the front
//  page.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature\Play;

use App\Models\Pages;
use Tests\TestCase;








// -----------------------------------------------------------
// PageTest
// -----------------------------------------------------------
//
// The pages are created directly; the control panel that
// writes them is Admin\PagesTest's.
//
// Used by:
//   - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------

final class PageTest extends TestCase
{






    // -----------------------------------------------------------
    // test_a_page_is_shown_by_its_alias
    // -----------------------------------------------------------
    //
    // The page renders its name as the title and its HTML as
    // written.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_page_is_shown_by_its_alias(): void
    {
        Pages::create(['name' => 'Rules of Play', 'content' => '<p>Play <strong>fair</strong>.</p>', 'alias' => 'rules']);

        $response = $this->get('/page/rules');

        $response->assertOk();
        $response->assertViewIs('users.page');
        $response->assertSee('<h1 class="h1 pb-4">Rules of Play</h1>', false);
        $response->assertSee('<p>Play <strong>fair</strong>.</p>', false);
    }






    // -----------------------------------------------------------
    // test_members_see_the_pages_too
    // -----------------------------------------------------------
    //
    // Pages need no session but refuse none either: a
    // signed-in player gets the same page.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_members_see_the_pages_too(): void
    {
        Pages::create(['name' => 'About the Lab', 'content' => '<p>Hello.</p>', 'alias' => 'about']);

        $this->signIn($this->makePlayer())->get('/page/about')->assertOk()->assertSee('About the Lab');
    }






    // -----------------------------------------------------------
    // test_an_unknown_alias_leads_to_the_front_page
    // -----------------------------------------------------------
    //
    // An alias that names no page redirects to the front page
    // rather than answering 404.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_unknown_alias_leads_to_the_front_page(): void
    {
        $this->get('/page/no-such-page')->assertRedirect('/');
    }
}
