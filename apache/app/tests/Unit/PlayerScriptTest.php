<?php

// -----------------------------------------------------------
//  [*] PlayerScriptTest — what custom.js keeps and holds back
//
//  The player script custom.js runs in the browser, where
//  the suite does not go, so these tests read the script
//  itself for two of its rules. The points filter keeps its
//  bounds in cookies of its own names: the cookies of its
//  older version held the prices of their day, and would
//  hide dearer challenges added since. And a form sent
//  behind reCAPTCHA is held back from a second press only
//  for a while: a send cancelled in the browser would
//  otherwise leave it dead until a reload.
//
//  Used by:
//    - PHPUnit — the Unit suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Unit;

use PHPUnit\Framework\TestCase;








// -----------------------------------------------------------
// PlayerScriptTest
// -----------------------------------------------------------
//
// Reads custom.js from the application's folder, without
// booting the application.
//
//   script — the text of custom.js
//
// Used by:
//   - PHPUnit — the Unit suite (runTests.sh)
// -----------------------------------------------------------

final class PlayerScriptTest extends TestCase
{

    // The cookies the filter's older version wrote
    private const OLD_COOKIES = ['filter_category', 'filter_points_from', 'filter_points_to'];






    // -----------------------------------------------------------
    // script
    // -----------------------------------------------------------
    //
    // The text of custom.js as the repository holds it, which
    // the player bundles end with (PlayerBundleTest).
    //
    // Used by:
    //   - the tests below
    // -----------------------------------------------------------

    private static function script(): string
    {
        return file_get_contents(dirname(__DIR__, 2) . '/resources/views/assets/js/custom.js');
    }






    // -----------------------------------------------------------
    // test_the_points_filter_reads_none_of_the_old_cookies
    // -----------------------------------------------------------
    //
    // The filter reads and writes cookies of new names only,
    // and drops the old ones unread.
    //
    // Used by:
    //   - PHPUnit — the Unit suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_points_filter_reads_none_of_the_old_cookies(): void
    {
        preg_match_all("/Cookies\\.(get|set)\\('([^']+)'/", self::script(), $uses);

        $this->assertNotEmpty($uses[2]);
        $this->assertSame([], array_values(array_intersect(array_unique($uses[2]), self::OLD_COOKIES)));
        foreach (self::OLD_COOKIES as $cookie) {
            $this->assertStringContainsString("Cookies.remove('{$cookie}')", self::script());
        }
    }






    // -----------------------------------------------------------
    // test_a_held_back_form_is_let_go_after_a_while
    // -----------------------------------------------------------
    //
    // A form behind reCAPTCHA, marked as being sent at its
    // first press, has the mark taken off by a timer of five
    // to sixty seconds as well as when the page is shown
    // again: a send cancelled in the browser, or a token that
    // never comes, would otherwise leave the form dead until a
    // reload.
    //
    // Used by:
    //   - PHPUnit — the Unit suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_held_back_form_is_let_go_after_a_while(): void
    {
        $found = preg_match("/setTimeout\\(function\\(\\) \\{\\s*form\\.removeAttribute\\('data-sending'\\);\\s*\\}, (\\d+)\\);/", self::script(), $timer);

        $this->assertSame(1, $found);
        $this->assertGreaterThanOrEqual(5000, (int) $timer[1]);
        $this->assertLessThanOrEqual(60000, (int) $timer[1]);
    }
}
