<?php

// -----------------------------------------------------------
//  [*] PlayerBundleTest — custom.js inside the player bundle
//
//  The player pages load one script bundle, app.js and its
//  copy app.min.js under public/assets/js, which
//  webpack.mix.js builds from the vendor scripts with the
//  lab's own custom.js last. The repository carries no
//  node_modules, so a change to custom.js reaches the
//  bundles by hand, by swapping their tail. These tests hold
//  both bundles to the repository's custom.js, without
//  booting the application.
//
//  Used by:
//    - PHPUnit — the Unit suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;








// -----------------------------------------------------------
// PlayerBundleTest
// -----------------------------------------------------------
//
// Reads the files from the application's folder.
//
// Used by:
//   - PHPUnit — the Unit suite (runTests.sh)
// -----------------------------------------------------------

final class PlayerBundleTest extends TestCase
{






    // -----------------------------------------------------------
    // test_the_bundle_ends_with_the_custom_script
    // -----------------------------------------------------------
    //
    // Each bundle ends with custom.js exactly as the
    // repository holds it, so the player pages run the points
    // filter, the reCAPTCHA forms and the sign-out links of
    // that file.
    //
    // Used by:
    //   - PHPUnit — the Unit suite (runTests.sh)
    // -----------------------------------------------------------

    #[DataProvider('bundles')]
    public function test_the_bundle_ends_with_the_custom_script(string $bundle): void
    {
        $root = dirname(__DIR__, 2);

        $this->assertStringEndsWith(
            file_get_contents("{$root}/resources/views/assets/js/custom.js"),
            file_get_contents("{$root}/public/assets/js/{$bundle}")
        );
    }






    // -----------------------------------------------------------
    // bundles
    // -----------------------------------------------------------
    //
    // The two bundles: the one webpack.mix.js builds, and the
    // copy the player layout loads.
    //
    // Used by:
    //   - test_the_bundle_ends_with_the_custom_script (above)
    // -----------------------------------------------------------

    public static function bundles(): array
    {
        return [
            'app.js'     => ['app.js'],
            'app.min.js' => ['app.min.js'],
        ];
    }
}
