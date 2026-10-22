<?php

// -----------------------------------------------------------
//  [*] ErrorPagesTest — what visitors see when things go wrong
//
//  Missing pages and server errors render the lab's own error
//  pages. With APP_DEBUG off, as in production, a server
//  error shows the bare "Error 500" page and nothing of the
//  exception behind it — no message, no stack trace, no
//  file names.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;








// -----------------------------------------------------------
// ErrorPagesTest
// -----------------------------------------------------------
//
// The server error comes from a route the test registers for
// itself; it exists only in that test's application.
//
// Used by:
//   - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------

final class ErrorPagesTest extends TestCase
{






    // -----------------------------------------------------------
    // test_an_unknown_address_gets_the_404_page
    // -----------------------------------------------------------
    //
    // An address no route serves answers 404 with the lab's
    // page, for guests and players alike.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_unknown_address_gets_the_404_page(): void
    {
        $this->get('/no-such-page')->assertNotFound()->assertSee('Error 404')->assertSee('Page not found');

        $this->signIn($this->makePlayer())->get('/user/no-such-page')->assertNotFound()->assertSee('Page not found');
    }






    // -----------------------------------------------------------
    // test_a_server_error_shows_nothing_of_the_exception
    // -----------------------------------------------------------
    //
    // An exception inside a request renders the 500 page, and
    // neither its message nor the code's location reaches the
    // visitor.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_server_error_shows_nothing_of_the_exception(): void
    {
        Route::middleware('web')->get('/__failing-route', function () {
            throw new RuntimeException('database password is hunter2');
        });

        $response = $this->get('/__failing-route');

        $response->assertStatus(500);
        $response->assertSee('Error 500');
        $response->assertSee('Internal Server Error');
        $response->assertDontSee('hunter2');
        $response->assertDontSee('RuntimeException');
        $response->assertDontSee('ErrorPagesTest.php');
    }
}
