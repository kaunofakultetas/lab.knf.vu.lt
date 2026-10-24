<?php

// -----------------------------------------------------------
//  [*] DashboardDefectsTest — open defects of the dashboard
//
//  The player dashboard declares a PHP function while it
//  renders, so a process can render it only once; its open
//  defects live here, each test in a process of its own.
//  KnownDefects reports a test incomplete while its defect
//  is open and fails it once a fix lands.
//
//  Used by:
//    - PHPUnit — the Defects suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Defects;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\Support\KnownDefects;
use Tests\TestCase;








// -----------------------------------------------------------
// DashboardDefectsTest
// -----------------------------------------------------------
//
// LAB-39.
//
// Used by:
//   - PHPUnit — the Defects suite (runTests.sh)
// -----------------------------------------------------------

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class DashboardDefectsTest extends TestCase
{

    // assertDefectStillOpen, which reports a test incomplete
    // while its defect is open
    use KnownDefects;






    // -----------------------------------------------------------
    // test_lab_39_the_points_filter_starts_wide_enough
    // -----------------------------------------------------------
    //
    // Known bug LAB-39 (medium): the dashboard's points filter
    // hides challenges it should show. Its bounds start at 5
    // and 250 (the min and max inputs of
    // users/dashboard.blade.php) while a challenge may be
    // worth any number of points, and every category button,
    // Any included, filters by them (custom.js), so a
    // 500-point challenge vanishes at the first click. Worse,
    // a bound the player clears is read as NaN, saved in its
    // cookie and compared with every price, so every card is
    // hidden on that visit and on every later one, until the
    // player types both bounds again. That half lives in the
    // script alone, where PHPUnit cannot reach; this test pins
    // the bounds the page starts with. Fix: start the bounds
    // from the lowest and highest price on the page, and treat
    // an empty or non-numeric bound as no bound, in the filter
    // and in the cookies.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_39_the_points_filter_starts_wide_enough(): void
    {
        $this->makeChallenge(['points' => 500]);
        $this->makeChallenge(['points' => 3]);

        $html = $this->signIn($this->makePlayer())->get('/user/dashboard')->getContent();
        preg_match('/id="min"[^>]*value="(\d+)"/', $html, $min);
        preg_match('/id="max"[^>]*value="(\d+)"/', $html, $max);

        $this->assertDefectStillOpen('LAB-39', function () use ($min, $max) {
            $this->assertLessThanOrEqual(3, isset($min[1]) ? (int) $min[1] : PHP_INT_MIN, 'the lower bound hides the 3-point challenge');
            $this->assertGreaterThanOrEqual(500, isset($max[1]) ? (int) $max[1] : PHP_INT_MAX, 'the upper bound hides the 500-point challenge');
        });
    }
}
