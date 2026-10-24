<?php

// -----------------------------------------------------------
//  [*] EnvFileTest — the .env baked into the image
//
//  The image's .env sets each key once. Of a key set twice
//  the later line wins, while a reader of the file stops at
//  the first: SESSION_LIFETIME once read 120 minutes beside
//  the other session keys and was set to 2160 again at the
//  end of the file. A lab session lasts 36 hours, as long
//  as the image keeps a SimpleSAMLphp session file.
//
//  Used by:
//    - PHPUnit — the Unit suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Unit;

use PHPUnit\Framework\TestCase;








// -----------------------------------------------------------
// EnvFileTest
// -----------------------------------------------------------
//
// Reads the file from the application's folder, without
// booting the application, and never prints a value but
// the session lifetime's.
//
//   settings — the keys the file sets, with their values
//
// Used by:
//   - PHPUnit — the Unit suite (runTests.sh)
// -----------------------------------------------------------

final class EnvFileTest extends TestCase
{






    // -----------------------------------------------------------
    // settings
    // -----------------------------------------------------------
    //
    // Every line of the .env that sets a key, as its key and
    // value, in the file's order; comments and blank lines
    // are skipped, and the file's CRLF line ends trimmed.
    //
    // Used by:
    //   - the tests below
    // -----------------------------------------------------------

    private static function settings(): array
    {
        $settings = [];
        foreach (file(dirname(__DIR__, 2) . '/.env', FILE_IGNORE_NEW_LINES) as $line) {
            if (preg_match('/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=(.*)$/', rtrim($line, "\r"), $match)) {
                $settings[] = [$match[1], trim($match[2])];
            }
        }

        return $settings;
    }






    // -----------------------------------------------------------
    // test_every_key_is_set_once
    // -----------------------------------------------------------
    //
    // No key of the .env is set on two lines.
    //
    // Used by:
    //   - PHPUnit — the Unit suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_every_key_is_set_once(): void
    {
        $keys = array_column(self::settings(), 0);

        $this->assertNotEmpty($keys);
        $this->assertSame([], array_keys(array_filter(array_count_values($keys), fn (int $count) => $count > 1)));
    }






    // -----------------------------------------------------------
    // test_a_lab_session_lasts_36_hours
    // -----------------------------------------------------------
    //
    // SESSION_LIFETIME is set once, to 2160 minutes — the 36
    // hours of a lab session.
    //
    // Used by:
    //   - PHPUnit — the Unit suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_lab_session_lasts_36_hours(): void
    {
        $lifetimes = array_values(array_map(
            fn (array $setting) => $setting[1],
            array_filter(self::settings(), fn (array $setting) => $setting[0] === 'SESSION_LIFETIME')
        ));

        $this->assertSame(['2160'], $lifetimes);
    }
}
