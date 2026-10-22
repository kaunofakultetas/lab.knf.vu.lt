<?php

// -----------------------------------------------------------
//  [*] OrdinalTest — the place names of the leaderboards
//
//  Every leaderboard prints places as English ordinals — 1st,
//  2nd, 3rd, then th, with 11th to 13th as the exceptions —
//  and the player sidebar picks its trophy icon by comparing
//  against those very strings. The app carries three copies
//  of the function: Users\SettingsController for the player
//  pages, Admins\LeaderboardController for the admin
//  dashboard and Admins\SettingsController for the admin
//  group leaderboard. These tests hold all three to the same
//  answers, without booting the application.
//
//  Used by:
//    - PHPUnit — the Unit suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Unit;

use App\Http\Controllers\Admins\LeaderboardController as AdminLeaderboard;
use App\Http\Controllers\Admins\SettingsController as AdminSettings;
use App\Http\Controllers\Users\SettingsController as PlayerSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;








// -----------------------------------------------------------
// OrdinalTest
// -----------------------------------------------------------
//
// Calls the three static copies directly; none of them needs
// the application.
//
// Used by:
//   - PHPUnit — the Unit suite (runTests.sh)
// -----------------------------------------------------------

final class OrdinalTest extends TestCase
{






    // -----------------------------------------------------------
    // test_every_copy_names_the_place_the_same_way
    // -----------------------------------------------------------
    //
    // Each place of the provider comes out as the expected
    // ordinal from all three copies.
    //
    // Used by:
    //   - PHPUnit — the Unit suite (runTests.sh)
    // -----------------------------------------------------------

    #[DataProvider('places')]
    public function test_every_copy_names_the_place_the_same_way(int $place, string $name): void
    {
        $this->assertSame($name, PlayerSettings::ordinal($place));
        $this->assertSame($name, AdminLeaderboard::ordinal($place));
        $this->assertSame($name, AdminSettings::ordinal($place));
    }






    // -----------------------------------------------------------
    // places
    // -----------------------------------------------------------
    //
    // The first places, the teens that break the pattern, and
    // the hundreds where the pattern repeats.
    //
    // Used by:
    //   - test_every_copy_names_the_place_the_same_way (above)
    // -----------------------------------------------------------

    public static function places(): array
    {
        return [
            '1st'    => [1, '1st'],
            '2nd'    => [2, '2nd'],
            '3rd'    => [3, '3rd'],
            '4th'    => [4, '4th'],
            '9th'    => [9, '9th'],
            '10th'   => [10, '10th'],
            '11th'   => [11, '11th'],
            '12th'   => [12, '12th'],
            '13th'   => [13, '13th'],
            '14th'   => [14, '14th'],
            '21st'   => [21, '21st'],
            '22nd'   => [22, '22nd'],
            '23rd'   => [23, '23rd'],
            '101st'  => [101, '101st'],
            '111th'  => [111, '111th'],
            '112th'  => [112, '112th'],
            '113th'  => [113, '113th'],
            '1002nd' => [1002, '1002nd'],
        ];
    }
}
