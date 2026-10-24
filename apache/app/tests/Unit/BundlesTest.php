<?php

// -----------------------------------------------------------
//  [*] BundlesTest — what the script and style bundles carry
//
//  The pages load prebuilt bundles from public/assets — the
//  scripts app.min.js and app_admin.min.js, the styles
//  app.min.css and app_admin.min.css — which webpack.mix.js
//  joins from the vendor files and the lab's own scripts.
//  The repository carries no node_modules, so the bundles
//  are changed by hand. Moment, Moment Timezone, the jQuery
//  countdown and the date range picker were cut from them,
//  as no page used them; these tests keep them out, keep
//  each .min copy equal to its bundle, and keep
//  webpack.mix.js naming only sources the repository holds.
//
//  Used by:
//    - PHPUnit — the Unit suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;








// -----------------------------------------------------------
// BundlesTest
// -----------------------------------------------------------
//
// Reads the files from the application's folder, without
// booting the application.
//
//   bundles  — every bundle, with its .min copy
//   path     — a file of the application's folder
//
// Used by:
//   - PHPUnit — the Unit suite (runTests.sh)
// -----------------------------------------------------------

final class BundlesTest extends TestCase
{

    // A line each dropped library carries, by name: its
    // banner, an error message or a name of its own
    private const DROPPED = [
        'moment'                 => 'isMoment',
        'moment-timezone'        => 'Moment Timezone requires Moment.js',
        'jquery.countdown'       => 'The Final Countdown for jQuery',
        'daterangepicker'        => 'Dan Grossman',
        'daterangepicker styles' => '.daterangepicker',
    ];






    // -----------------------------------------------------------
    // path
    // -----------------------------------------------------------
    //
    // The full path of a file given relative to the
    // application's folder.
    //
    // Used by:
    //   - the tests below
    // -----------------------------------------------------------

    private static function path(string $file): string
    {
        return dirname(__DIR__, 2) . '/' . $file;
    }






    // -----------------------------------------------------------
    // test_no_bundle_carries_a_dropped_library
    // -----------------------------------------------------------
    //
    // Neither a bundle nor its .min copy holds a line of a
    // dropped library.
    //
    // Used by:
    //   - PHPUnit — the Unit suite (runTests.sh)
    // -----------------------------------------------------------

    #[DataProvider('bundles')]
    public function test_no_bundle_carries_a_dropped_library(string $bundle, string $copy): void
    {
        foreach ([$bundle, $copy] as $file) {
            $content = file_get_contents(self::path($file));
            foreach (self::DROPPED as $library => $line) {
                $this->assertStringNotContainsString($line, $content, "{$file} carries {$library}");
            }
        }
    }






    // -----------------------------------------------------------
    // test_each_min_copy_is_its_bundle
    // -----------------------------------------------------------
    //
    // The layouts load the .min copies, so a change made by
    // hand to a bundle reaches its copy too: each copy is its
    // bundle, byte for byte.
    //
    // Used by:
    //   - PHPUnit — the Unit suite (runTests.sh)
    // -----------------------------------------------------------

    #[DataProvider('bundles')]
    public function test_each_min_copy_is_its_bundle(string $bundle, string $copy): void
    {
        $this->assertSame(sha1_file(self::path($bundle)), sha1_file(self::path($copy)));
    }






    // -----------------------------------------------------------
    // test_the_mix_file_names_only_sources_the_repository_holds
    // -----------------------------------------------------------
    //
    // Every source of the repository that webpack.mix.js joins
    // into a bundle exists — a build from it then finds them
    // all — and none is a dropped library. The node_modules
    // sources come from npm when the bundles are built, and
    // are left out.
    //
    // Used by:
    //   - PHPUnit — the Unit suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_mix_file_names_only_sources_the_repository_holds(): void
    {
        preg_match_all("~'(resources/[^']+)'~", file_get_contents(self::path('webpack.mix.js')), $sources);

        $this->assertNotEmpty($sources[1]);
        foreach ($sources[1] as $source) {
            $this->assertFileExists(self::path($source));
            foreach (array_keys(self::DROPPED) as $library) {
                $this->assertStringNotContainsString(strtok($library, ' '), basename($source), "webpack.mix.js joins {$library}");
            }
        }
    }






    // -----------------------------------------------------------
    // bundles
    // -----------------------------------------------------------
    //
    // Every bundle the layouts load, with its .min copy: the
    // player and panel scripts and styles.
    //
    // Used by:
    //   - test_no_bundle_carries_a_dropped_library and
    //     test_each_min_copy_is_its_bundle (above)
    // -----------------------------------------------------------

    public static function bundles(): array
    {
        return [
            'app.js'        => ['public/assets/js/app.js', 'public/assets/js/app.min.js'],
            'app_admin.js'  => ['public/assets/js/app_admin.js', 'public/assets/js/app_admin.min.js'],
            'app.css'       => ['public/assets/css/app.css', 'public/assets/css/app.min.css'],
            'app_admin.css' => ['public/assets/css/app_admin.css', 'public/assets/css/app_admin.min.css'],
        ];
    }






    // -----------------------------------------------------------
    // test_no_source_map_of_a_dropped_library_is_left
    // -----------------------------------------------------------
    //
    // The scripts folder holds no source map of a dropped
    // library, which only the library's own code pointed to.
    //
    // Used by:
    //   - PHPUnit — the Unit suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_no_source_map_of_a_dropped_library_is_left(): void
    {
        foreach (glob(self::path('public/assets/js/*.map')) as $map) {
            foreach (array_keys(self::DROPPED) as $library) {
                $this->assertStringNotContainsString(strtok($library, ' '), basename($map), basename($map) . " belongs to {$library}");
            }
        }
        $this->assertFileDoesNotExist(self::path('public/assets/js/moment.min.js.map'));
    }
}
