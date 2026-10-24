<?php

// -----------------------------------------------------------
//  [*] ElapsedTimeTest — "3 days ago" on the player pages
//
//  The leaderboards, the solve history in the player sidebar
//  and the admin dashboard print how long ago something
//  happened: the largest unit that is not zero, singular or
//  plural, weeks folded out of the days — or every unit,
//  comma-separated, when asked for the full form. Like the
//  ordinals, the function exists three times, in
//  Users\SettingsController, Admins\LeaderboardController
//  and Admins\SettingsController, and all three are held to
//  the same answers here.
//
//  Used by:
//    - PHPUnit — the Unit suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Unit;

use App\Http\Controllers\Admins\LeaderboardController as AdminLeaderboard;
use App\Http\Controllers\Admins\SettingsController as AdminSettings;
use App\Http\Controllers\Users\SettingsController as PlayerSettings;
use DateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;








// -----------------------------------------------------------
// ElapsedTimeTest
// -----------------------------------------------------------
//
// The function measures from the real clock, so the moments
// handed to it carry microseconds: the age it sees then
// differs from the intended one by the microseconds between
// two lines, never by a whole second. The tests run in UTC,
// where no daylight-saving change can stretch a day.
//
// Used by:
//   - PHPUnit — the Unit suite (runTests.sh)
// -----------------------------------------------------------

final class ElapsedTimeTest extends TestCase
{

    // The time zone the tests found, put back after each test
    private string $zone = 'UTC';






    // -----------------------------------------------------------
    // setUp
    // -----------------------------------------------------------
    //
    // Switches PHP to UTC — a Feature test run earlier in the
    // same process leaves the application's Europe/Vilnius
    // behind, where a day across a clock change is 23 or 25
    // hours long.
    //
    // Used by:
    //   - PHPUnit, before every test
    // -----------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();
        $this->zone = date_default_timezone_get();
        date_default_timezone_set('UTC');
    }






    // -----------------------------------------------------------
    // tearDown
    // -----------------------------------------------------------
    //
    // Puts the time zone back.
    //
    // Used by:
    //   - PHPUnit, after every test
    // -----------------------------------------------------------

    protected function tearDown(): void
    {
        date_default_timezone_set($this->zone);
        parent::tearDown();
    }






    // -----------------------------------------------------------
    // test_every_copy_words_an_age_the_same_way
    // -----------------------------------------------------------
    //
    // A moment the given offset in the past reads as the
    // expected words from all three copies.
    //
    // Used by:
    //   - PHPUnit — the Unit suite (runTests.sh)
    // -----------------------------------------------------------

    #[DataProvider('ages')]
    public function test_every_copy_words_an_age_the_same_way(string $offset, string $words): void
    {
        $then = (new DateTime())->modify($offset)->format('Y-m-d H:i:s.u');

        $this->assertSame($words, PlayerSettings::time_elapsed_string($then));
        $this->assertSame($words, AdminLeaderboard::time_elapsed_string($then));
        $this->assertSame($words, AdminSettings::time_elapsed_string($then));
    }






    // -----------------------------------------------------------
    // ages
    // -----------------------------------------------------------
    //
    // An offset for DateTime::modify and the words it should
    // produce. The month and year cases use 45 and 400 days,
    // which fall into the same unit whatever today's date is.
    //
    // Used by:
    //   - test_every_copy_words_an_age_the_same_way (above)
    // -----------------------------------------------------------

    public static function ages(): array
    {
        return [
            'now'        => ['-0 seconds', 'just now'],
            'one second' => ['-1 second', '1 second ago'],
            'seconds'    => ['-30 seconds', '30 seconds ago'],
            'one minute' => ['-60 seconds', '1 minute ago'],
            '90 seconds' => ['-90 seconds', '1 minute ago'],
            'hours'      => ['-2 hours', '2 hours ago'],
            'one day'    => ['-1 day', '1 day ago'],
            'days'       => ['-6 days', '6 days ago'],
            'one week'   => ['-7 days', '1 week ago'],
            'weeks'      => ['-15 days', '2 weeks ago'],
            'one month'  => ['-45 days', '1 month ago'],
            'one year'   => ['-400 days', '1 year ago'],
        ];
    }






    // -----------------------------------------------------------
    // test_the_full_form_lists_every_unit
    // -----------------------------------------------------------
    //
    // Asked for the full form, every copy names each non-zero
    // unit, largest first, separated by commas.
    //
    // Used by:
    //   - PHPUnit — the Unit suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_full_form_lists_every_unit(): void
    {
        $then = (new DateTime())->modify('-1 day -2 hours -3 minutes')->format('Y-m-d H:i:s.u');

        $words = '1 day, 2 hours, 3 minutes ago';
        $this->assertSame($words, PlayerSettings::time_elapsed_string($then, true));
        $this->assertSame($words, AdminLeaderboard::time_elapsed_string($then, true));
        $this->assertSame($words, AdminSettings::time_elapsed_string($then, true));
    }






    // -----------------------------------------------------------
    // test_no_copy_raises_a_deprecation
    // -----------------------------------------------------------
    //
    // The weeks are counted beside PHP's DateInterval, not in
    // a property added to it: PHP 8.2 deprecated such dynamic
    // properties, and PHP 9 is expected to refuse them, which
    // would end every page that shows an age in a server
    // error. No copy raises a deprecation, with weeks to word
    // or without.
    //
    // Used by:
    //   - PHPUnit — the Unit suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_no_copy_raises_a_deprecation(): void
    {
        $deprecations = [];
        set_error_handler(function (int $level, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;
            return true;
        }, E_DEPRECATED);

        try {
            foreach (['-15 days -2 hours', '-3 hours'] as $offset) {
                $then = (new DateTime())->modify($offset)->format('Y-m-d H:i:s.u');
                PlayerSettings::time_elapsed_string($then, true);
                AdminLeaderboard::time_elapsed_string($then, true);
                AdminSettings::time_elapsed_string($then, true);
            }
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $deprecations);
    }






    // -----------------------------------------------------------
    // test_every_copy_reads_unix_seconds
    // -----------------------------------------------------------
    //
    // Unix seconds after an @, which the pages hand over as
    // the local times in the database repeat an hour when the
    // clocks go back in October, read as their real age by
    // all three copies, in the application's Europe/Vilnius
    // as anywhere.
    //
    // Used by:
    //   - PHPUnit — the Unit suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_every_copy_reads_unix_seconds(): void
    {
        date_default_timezone_set('Europe/Vilnius');
        $then = '@' . (time() - 300);

        $this->assertSame('5 minutes ago', PlayerSettings::time_elapsed_string($then));
        $this->assertSame('5 minutes ago', AdminLeaderboard::time_elapsed_string($then));
        $this->assertSame('5 minutes ago', AdminSettings::time_elapsed_string($then));
    }
}
