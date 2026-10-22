<?php

// -----------------------------------------------------------
//  [*] UploadDefectsTest — open defects in the logo uploads
//
//  Each test states the behaviour the lab wants where the
//  partner logo upload falls short today, under a LAB-nn id;
//  KnownDefects reports it incomplete while the defect is
//  open and fails it once a fix lands.
//
//  Used by:
//    - PHPUnit — the Defects suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Defects;

use App\Models\Partners;
use Illuminate\Http\UploadedFile;
use Tests\Support\KnownDefects;
use Tests\TestCase;








// -----------------------------------------------------------
// UploadDefectsTest
// -----------------------------------------------------------
//
// LAB-17. The uploads are real files with PNG bytes, removed
// again after the test, like in PartnersTest.
//
//   uploadLogo — stores a partner with a fresh logo
//
// Used by:
//   - PHPUnit — the Defects suite (runTests.sh)
// -----------------------------------------------------------

final class UploadDefectsTest extends TestCase
{

    use KnownDefects;

    // The files in the upload folder before the test, kept
    // when the test cleans up after itself
    private array $before = [];






    // -----------------------------------------------------------
    // setUp
    // -----------------------------------------------------------
    //
    // Notes what the upload folder holds before the test.
    //
    // Used by:
    //   - PHPUnit, before every test
    // -----------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();
        $this->before = glob(storage_path('app/public/*')) ?: [];
    }






    // -----------------------------------------------------------
    // tearDown
    // -----------------------------------------------------------
    //
    // Removes the files the test stored.
    //
    // Used by:
    //   - PHPUnit, after every test
    // -----------------------------------------------------------

    protected function tearDown(): void
    {
        foreach (array_diff(glob(storage_path('app/public/*')) ?: [], $this->before) as $file) {
            unlink($file);
        }
        parent::tearDown();
    }






    // -----------------------------------------------------------
    // uploadLogo
    // -----------------------------------------------------------
    //
    // Stores a partner of the given name with a one-pixel PNG
    // as its logo, and returns the stored file name.
    //
    // Used by:
    //   - the test below
    // -----------------------------------------------------------

    private function uploadLogo(string $name): string
    {
        $path = tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
        $this->post('/control-panel/dialog/partners/store', ['name' => $name, 'picture' => new UploadedFile($path, 'logo.png', null, null, true)]);
        return Partners::where('name', $name)->value('picture');
    }






    // -----------------------------------------------------------
    // test_lab_17_two_logos_never_share_a_file
    // -----------------------------------------------------------
    //
    // Known bug LAB-17 (low): two logos uploaded within the
    // same second are stored under the same name, and the
    // second overwrites the first partner's picture. The name
    // is a hash of the current second only
    // (PartnersController::store and update, line 174 and
    // 248). Fix: hash something unique, such as a random
    // string. Three pairs are tried, so a pair that happens
    // to straddle a second cannot hide the defect.
    //
    // Used by:
    //   - PHPUnit — the Defects suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_17_two_logos_never_share_a_file(): void
    {
        $this->signInAdmin($this->makeAdmin(true));
        $pairs = [];
        foreach (range(1, 3) as $n) {
            $pairs[] = [$this->uploadLogo("First {$n}"), $this->uploadLogo("Second {$n}")];
        }

        $this->assertDefectStillOpen('LAB-17', function () use ($pairs) {
            foreach ($pairs as [$first, $second]) {
                $this->assertNotSame($first, $second, 'two partners share one logo file');
            }
        });
    }
}
