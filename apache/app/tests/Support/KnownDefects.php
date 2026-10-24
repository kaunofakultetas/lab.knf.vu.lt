<?php

// -----------------------------------------------------------
//  [*] KnownDefects — pinning a bug before it is fixed
//
//  A defect found but not yet fixed gets its regression test
//  up front, stating the behaviour the lab wants. While the
//  defect is open the test is reported incomplete under the
//  defect's id, with the first line of the failure, so the
//  run stays green and lists every open defect at its end.
//  The day a fix lands the test fails on purpose — the cue to
//  move it into its home file without the id. It is the
//  PHPUnit form of the expectedFailure convention that the
//  Python suites of the other house stacks follow.
//
//  Used by:
//    - Defects\AdminDefectsTest
//    - Defects\PlayerDefectsTest
//    - Defects\DashboardDefectsTest
//    - Defects\AccountDefectsTest
//    - Defects\SsoDefectsTest
// -----------------------------------------------------------


namespace Tests\Support;

use Closure;
use PHPUnit\Framework\AssertionFailedError;








// -----------------------------------------------------------
// KnownDefects
// -----------------------------------------------------------
//
// Only assertion failures count as "still open"; any other
// error inside the wanted behaviour — a broken test, an
// exception the test did not expect — is reported as the
// error it is, so a defect test cannot hide its own bugs.
// A defect that shows as an exception from a direct call is
// turned into a failure by the test itself.
//
// Used by:
//   - Defects\AdminDefectsTest
//   - Defects\PlayerDefectsTest
//   - Defects\DashboardDefectsTest
//   - Defects\AccountDefectsTest
//   - Defects\SsoDefectsTest
// -----------------------------------------------------------

trait KnownDefects
{






    // -----------------------------------------------------------
    // assertDefectStillOpen
    // -----------------------------------------------------------
    //
    // Runs the assertions describing the wanted behaviour.
    // While they fail, marks the test incomplete with the
    // defect's id; once they pass, fails it, as the defect is
    // fixed and the test now belongs in its home file.
    //
    // Used by:
    //   - Defects\AdminDefectsTest
    //   - Defects\PlayerDefectsTest
    //   - Defects\DashboardDefectsTest
    //   - Defects\AccountDefectsTest
    //   - Defects\SsoDefectsTest
    // -----------------------------------------------------------

    protected function assertDefectStillOpen(string $defect, Closure $wanted): void
    {
        try {
            $wanted();
        } catch (AssertionFailedError $failure) {
            $this->markTestIncomplete("{$defect} is still open: " . strtok($failure->getMessage(), "\n"));
        }
        $this->fail("{$defect} behaves as wanted now: move this test into its home file and drop the defect id.");
    }
}
