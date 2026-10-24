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
// LAB-59.
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
    // test_lab_59_the_points_filter_keeps_no_bound_the_player_did_not_type
    // -----------------------------------------------------------
    //
    // Known bug LAB-59 (low): what is left of LAB-39. The
    // points boxes start filled with the page's cheapest and
    // dearest prices (Users\ChallengesController::index), and
    // a click on any category button, Any included, or on
    // Filter saves the boxes' text in cookies (custom.js), so
    // the prices of that moment become bounds the player never
    // typed. On every later load the cookies win over the
    // page's fresh prices, and a challenge added, shown or
    // repriced outside the old range stays hidden for the rest
    // of the browser session. Fix: leave the boxes empty, with
    // the page's prices as their placeholders, so an empty box
    // means no bound and a cookie keeps only what the player
    // typed.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_59_the_points_filter_keeps_no_bound_the_player_did_not_type(): void
    {
        $this->makeChallenge(['points' => 500]);
        $this->makeChallenge(['points' => 3]);

        $html = $this->signIn($this->makePlayer())->get('/user/dashboard')->getContent();
        preg_match('/<input[^>]*id="min"[^>]*>/', $html, $min);
        preg_match('/<input[^>]*id="max"[^>]*>/', $html, $max);

        $this->assertDefectStillOpen('LAB-59', function () use ($min, $max) {
            $this->assertDoesNotMatchRegularExpression('/value="\d+"/', $min[0] ?? '', 'the lower box starts filled');
            $this->assertStringContainsString('placeholder="3"', $min[0] ?? '');
            $this->assertDoesNotMatchRegularExpression('/value="\d+"/', $max[0] ?? '', 'the upper box starts filled');
            $this->assertStringContainsString('placeholder="500"', $max[0] ?? '');
        });
    }
}
