<?php

// -----------------------------------------------------------
//  [*] PartnersTest — the partners credited on challenges
//
//  Main admins keep the organisations challenges are credited
//  to, each with a logo the challenge page shows from
//  /upload. Since the upload hardening a logo must be a JPEG,
//  PNG, GIF or WebP image of at most 2 MB — judged by its
//  content, not by the name it was sent under — and it is
//  stored under a hashed name whose extension comes from that
//  content too, so a file can never choose its own extension
//  on the server. A new logo is required on every edit.
//
//  The uploads land in storage/app/public, which the tests
//  container mounts as a scratch tmpfs; the live container
//  mounts the stack's _DATA/apache/public there read-only.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature\Admin;

use App\Models\Partners;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;








// -----------------------------------------------------------
// PartnersTest
// -----------------------------------------------------------
//
// The uploads are real files with real image bytes: the
// image library Laravel's fake images need is not installed
// in the image, and a fake would report the type its name
// suggests instead of the type of its content. Files a test
// stores are removed after it.
//
//   upload — an uploaded file with the given name and bytes
//   png, jpeg — the bytes of tiny valid images
//
// Used by:
//   - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------

final class PartnersTest extends TestCase
{

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
    // upload
    // -----------------------------------------------------------
    //
    // A file as the browser would send it, under the given
    // name, holding the given bytes.
    //
    // Used by:
    //   - the upload tests below
    // -----------------------------------------------------------

    private function upload(string $name, string $bytes): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($path, $bytes);
        return new UploadedFile($path, $name, null, null, true);
    }






    // -----------------------------------------------------------
    // png
    // -----------------------------------------------------------
    //
    // A valid one-pixel PNG, padded to the given size with
    // trailing bytes image readers ignore.
    //
    // Used by:
    //   - the upload tests and badUploads (below)
    // -----------------------------------------------------------

    private static function png(int $size = 0): string
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
        return str_pad($png, $size, "\0");
    }






    // -----------------------------------------------------------
    // jpeg
    // -----------------------------------------------------------
    //
    // The opening and closing markers of a JPEG with empty
    // space between — enough for the type check, which reads
    // the content's signature.
    //
    // Used by:
    //   - the upload tests below
    // -----------------------------------------------------------

    private static function jpeg(): string
    {
        return "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00" . str_repeat("\x00", 64) . "\xFF\xD9";
    }






    // -----------------------------------------------------------
    // test_the_list_shows_every_partner_with_its_logo
    // -----------------------------------------------------------
    //
    // Each partner is listed with its logo from /upload and
    // its edit and delete actions.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_list_shows_every_partner_with_its_logo(): void
    {
        $partner = $this->makePartner(['name' => 'CERT-LT', 'picture' => 'cert-lt.png']);

        $response = $this->signInAdmin($this->makeAdmin(true))->get('/control-panel/partners');

        $response->assertOk();
        $response->assertSee('<td>CERT-LT</td>', false);
        $response->assertSee('src="https://lab.knf.vu.lt/upload/cert-lt.png"', false);
        $response->assertSee("partners_form('edit', {$partner->id})", false);
        $response->assertSee("/control-panel/partners/delete/{$partner->id}", false);
    }






    // -----------------------------------------------------------
    // test_the_new_partner_dialog_uploads_to_the_store_route
    // -----------------------------------------------------------
    //
    // The dialog is a multipart form with a name and a
    // required picture field.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_new_partner_dialog_uploads_to_the_store_route(): void
    {
        $response = $this->signInAdmin($this->makeAdmin(true))->get('/control-panel/dialog/partners/new');

        $response->assertSee('action="https://lab.knf.vu.lt/control-panel/dialog/partners/store"', false);
        $response->assertSee('enctype="multipart/form-data"', false);
        $response->assertSee('name="picture"', false);
    }






    // -----------------------------------------------------------
    // test_a_partner_is_stored_with_its_logo_under_a_hashed_name
    // -----------------------------------------------------------
    //
    // The logo is stored as 32 hex digits and the extension
    // its content calls for, byte for byte, and the partner
    // row points at it.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_partner_is_stored_with_its_logo_under_a_hashed_name(): void
    {
        $response = $this->signInAdmin($this->makeAdmin(true))
            ->from('/control-panel/partners')
            ->post('/control-panel/dialog/partners/store', ['name' => 'CERT-LT', 'picture' => $this->upload('logo.png', self::png())]);

        $response->assertRedirect('/control-panel/partners');
        $response->assertSessionHas('success', 'Created!');
        $partner = Partners::where('name', 'CERT-LT')->firstOrFail();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}\.png$/', $partner->picture);
        $this->assertSame(self::png(), file_get_contents(storage_path("app/public/{$partner->picture}")));
    }






    // -----------------------------------------------------------
    // test_a_jpeg_logo_is_stored_as_jpg
    // -----------------------------------------------------------
    //
    // JPEG content is always stored with the .jpg extension,
    // whatever the uploaded name said.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_jpeg_logo_is_stored_as_jpg(): void
    {
        $this->signInAdmin($this->makeAdmin(true))
            ->post('/control-panel/dialog/partners/store', ['name' => 'CERT-LT', 'picture' => $this->upload('Logo.JPEG', self::jpeg())]);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}\.jpg$/', Partners::where('name', 'CERT-LT')->value('picture'));
    }






    // -----------------------------------------------------------
    // test_the_stored_extension_follows_the_content
    // -----------------------------------------------------------
    //
    // A PNG sent under a .gif name is stored as .png: the name
    // the browser sends never decides the stored extension.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_stored_extension_follows_the_content(): void
    {
        $this->signInAdmin($this->makeAdmin(true))
            ->post('/control-panel/dialog/partners/store', ['name' => 'CERT-LT', 'picture' => $this->upload('logo.gif', self::png())]);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}\.png$/', Partners::where('name', 'CERT-LT')->value('picture'));
    }






    // -----------------------------------------------------------
    // test_a_file_sent_under_a_script_name_is_refused
    // -----------------------------------------------------------
    //
    // A file named like a PHP script is refused even when its
    // content is a real image — a PHP-and-PNG polyglot here —
    // so no such file is ever written.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_file_sent_under_a_script_name_is_refused(): void
    {
        $polyglot = self::png() . '<?php echo 1;';
        $this->signInAdmin($this->makeAdmin(true));

        foreach (['shell.php', 'shell.phtml', 'shell.phar', 'shell.PHP5'] as $name) {
            $this->post('/control-panel/dialog/partners/store', ['name' => 'Polyglot', 'picture' => $this->upload($name, $polyglot)])
                ->assertSessionHasErrors('picture');
        }

        $this->assertSame(0, Partners::count());
        $this->assertSame($this->before, glob(storage_path('app/public/*')) ?: []);
    }






    // -----------------------------------------------------------
    // test_only_small_images_are_accepted
    // -----------------------------------------------------------
    //
    // A missing name or picture, a picture whose content is no
    // accepted image — whatever its name — and an image over
    // 2 MB are refused with an error under the field; no
    // partner is stored and nothing is written.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    #[DataProvider('badUploads')]
    public function test_only_small_images_are_accepted(string $name, ?string $file, ?string $bytes, string $field): void
    {
        $fields = ['name' => $name];
        if ($file !== null) {
            $fields['picture'] = $this->upload($file, $bytes);
        }

        $response = $this->signInAdmin($this->makeAdmin(true))->post('/control-panel/dialog/partners/store', $fields);

        $response->assertSessionHasErrors($field);
        $this->assertSame(0, Partners::count());
        $this->assertSame($this->before, glob(storage_path('app/public/*')) ?: []);
    }






    // -----------------------------------------------------------
    // badUploads
    // -----------------------------------------------------------
    //
    // Partner name, uploaded file name and bytes (none for no
    // upload), and the field the error lands on.
    //
    // Used by:
    //   - test_only_small_images_are_accepted (above)
    // -----------------------------------------------------------

    public static function badUploads(): array
    {
        return [
            'no name'         => ['', 'logo.png', self::png(), 'name'],
            'no picture'      => ['CERT-LT', null, null, 'picture'],
            'script as png'   => ['CERT-LT', 'logo.png', '<?php system($_GET["c"]);', 'picture'],
            'text as png'     => ['CERT-LT', 'logo.png', 'just some text', 'picture'],
            'svg image'       => ['CERT-LT', 'logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"></svg>', 'picture'],
            'image over 2 MB' => ['CERT-LT', 'logo.png', self::png(2049 * 1024), 'picture'],
        ];
    }






    // -----------------------------------------------------------
    // test_the_edit_dialog_is_filled_with_the_partner
    // -----------------------------------------------------------
    //
    // The edit dialog carries the name and posts to the update
    // route with the partner's id.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_edit_dialog_is_filled_with_the_partner(): void
    {
        $partner = $this->makePartner(['name' => 'CERT-LT']);

        $response = $this->signInAdmin($this->makeAdmin(true))->get("/control-panel/dialog/partners/edit/{$partner->id}");

        $response->assertSee('action="https://lab.knf.vu.lt/control-panel/dialog/partners/update"', false);
        $response->assertSee('value="CERT-LT"', false);
        $response->assertSee("name=\"uid\" type=\"hidden\" value=\"{$partner->id}\"", false);
    }






    // -----------------------------------------------------------
    // test_an_update_replaces_the_name_and_the_logo
    // -----------------------------------------------------------
    //
    // The update stores the new name and a new logo under a
    // new hashed name.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_update_replaces_the_name_and_the_logo(): void
    {
        $partner = $this->makePartner(['name' => 'CERT-LT', 'picture' => 'old.png']);

        $response = $this->signInAdmin($this->makeAdmin(true))
            ->from('/control-panel/partners')
            ->post('/control-panel/dialog/partners/update', [
                'uid'     => $partner->id,
                'name'    => 'NRD CS',
                'picture' => $this->upload('new.gif', 'GIF89a' . "\x01\x00\x01\x00\x00\x00\x00;"),
            ]);

        $response->assertSessionHas('success', 'Updated!');
        $partner->refresh();
        $this->assertSame('NRD CS', $partner->name);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}\.gif$/', $partner->picture);
    }






    // -----------------------------------------------------------
    // test_an_update_needs_a_new_logo_and_a_numeric_id
    // -----------------------------------------------------------
    //
    // Without a picture, or with an id that is not a number,
    // the update is refused and the partner stays as it was.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_update_needs_a_new_logo_and_a_numeric_id(): void
    {
        $partner = $this->makePartner(['name' => 'CERT-LT']);
        $this->signInAdmin($this->makeAdmin(true));

        $this->post('/control-panel/dialog/partners/update', ['uid' => $partner->id, 'name' => 'NRD CS'])
            ->assertSessionHasErrors('picture');
        $this->post('/control-panel/dialog/partners/update', ['uid' => 'all', 'name' => 'NRD CS', 'picture' => $this->upload('logo.png', self::png())])
            ->assertSessionHasErrors('uid');

        $this->assertSame('CERT-LT', $partner->refresh()->name);
    }






    // -----------------------------------------------------------
    // test_a_partner_is_deleted
    // -----------------------------------------------------------
    //
    // Deleting removes the partner row.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_partner_is_deleted(): void
    {
        $partner = $this->makePartner();

        $response = $this->signInAdmin($this->makeAdmin(true))
            ->from('/control-panel/partners')
            ->post("/control-panel/partners/delete/{$partner->id}");

        $response->assertRedirect('/control-panel/partners');
        $response->assertSessionHas('success', 'Deleted!');
        $this->assertNull(Partners::find($partner->id));
    }






    // -----------------------------------------------------------
    // test_lab_admins_cannot_touch_partners
    // -----------------------------------------------------------
    //
    // Every partner route sends a lab admin back with the
    // permissions error, and nothing changes.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_lab_admins_cannot_touch_partners(): void
    {
        $partner = $this->makePartner(['name' => 'CERT-LT']);
        $this->signInAdmin($this->makeAdmin())->from('/control-panel/dashboard');

        $this->get("/control-panel/dialog/partners/edit/{$partner->id}")->assertRedirect('/control-panel/dashboard');
        $this->post('/control-panel/dialog/partners/store', ['name' => 'X', 'picture' => $this->upload('logo.png', self::png())])
            ->assertRedirect('/control-panel/dashboard');
        $this->post('/control-panel/dialog/partners/update', ['uid' => $partner->id, 'name' => 'X'])
            ->assertRedirect('/control-panel/dashboard');
        $this->post("/control-panel/partners/delete/{$partner->id}")->assertRedirect('/control-panel/dashboard');

        $this->assertErrorFlashed("You don't have permissions to do this!");
        $this->assertSame(1, Partners::count());
        $this->assertSame('CERT-LT', $partner->refresh()->name);
    }






    // -----------------------------------------------------------
    // test_two_logos_never_share_a_file
    // -----------------------------------------------------------
    //
    // Logos stored within the same second get files of their own:
    // the names are random, not drawn from the clock. Three pairs
    // are stored, so a pair straddling a second hides nothing.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_two_logos_never_share_a_file(): void
    {
        $this->signInAdmin($this->makeAdmin(true));

        foreach (range(1, 3) as $n) {
            foreach (["First {$n}", "Second {$n}"] as $name) {
                $this->post('/control-panel/dialog/partners/store', ['name' => $name, 'picture' => $this->upload('logo.png', self::png())]);
            }
            $this->assertNotSame(Partners::where('name', "First {$n}")->value('picture'), Partners::where('name', "Second {$n}")->value('picture'));
        }
    }
}
