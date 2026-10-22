<?php

// -----------------------------------------------------------
//  [*] ClientIpTest — the address booked with logins and solves
//
//  SettingsController::getUserIP picks the client address the
//  app stores as a player's last_ip and with every solved
//  challenge. It reads the request globals directly, in a
//  fixed order: a Cloudflare header first (copied over the
//  connection address and the Client-IP header as it goes),
//  then Client-IP, then X-Forwarded-For when it holds a
//  single valid address, and the connection address last.
//  Every one of those headers comes from the client — the
//  stack has no Cloudflare in front — so the stored address
//  is informative, never proof. These tests pin the order.
//
//  Used by:
//    - PHPUnit — the Unit suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Unit;

use App\Http\Controllers\Users\SettingsController;
use PHPUnit\Framework\TestCase;








// -----------------------------------------------------------
// ClientIpTest
// -----------------------------------------------------------
//
// Each test starts from a connection address of 172.18.7.2
// — the Caddy endpoint on the stack's network — and no proxy
// headers, and the request globals are restored afterwards.
//
// Used by:
//   - PHPUnit — the Unit suite (runTests.sh)
// -----------------------------------------------------------

final class ClientIpTest extends TestCase
{

    // The request globals as the test found them
    private array $server = [];






    // -----------------------------------------------------------
    // setUp
    // -----------------------------------------------------------
    //
    // Saves the request globals and leaves only the
    // connection address in place.
    //
    // Used by:
    //   - PHPUnit, before every test
    // -----------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();
        $this->server = $_SERVER;
        unset($_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['HTTP_CLIENT_IP'], $_SERVER['HTTP_X_FORWARDED_FOR']);
        $_SERVER['REMOTE_ADDR'] = '172.18.7.2';
    }






    // -----------------------------------------------------------
    // tearDown
    // -----------------------------------------------------------
    //
    // Restores the request globals.
    //
    // Used by:
    //   - PHPUnit, after every test
    // -----------------------------------------------------------

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        parent::tearDown();
    }






    // -----------------------------------------------------------
    // test_the_connection_address_is_used_without_proxy_headers
    // -----------------------------------------------------------
    //
    // With no header naming another address, the connection
    // address is the answer.
    //
    // Used by:
    //   - PHPUnit — the Unit suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_connection_address_is_used_without_proxy_headers(): void
    {
        $this->assertSame('172.18.7.2', SettingsController::getUserIP());
    }






    // -----------------------------------------------------------
    // test_a_single_forwarded_address_wins_over_the_connection
    // -----------------------------------------------------------
    //
    // X-Forwarded-For holding one valid address replaces the
    // connection address.
    //
    // Used by:
    //   - PHPUnit — the Unit suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_single_forwarded_address_wins_over_the_connection(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.4';

        $this->assertSame('198.51.100.4', SettingsController::getUserIP());
    }






    // -----------------------------------------------------------
    // test_a_forwarded_chain_is_ignored
    // -----------------------------------------------------------
    //
    // A comma-separated chain of proxies is not one valid
    // address, so the connection address is used instead.
    //
    // Used by:
    //   - PHPUnit — the Unit suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_forwarded_chain_is_ignored(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.4, 172.19.0.5';

        $this->assertSame('172.18.7.2', SettingsController::getUserIP());
    }






    // -----------------------------------------------------------
    // test_the_client_ip_header_wins_over_the_forwarded_one
    // -----------------------------------------------------------
    //
    // Client-IP is read before X-Forwarded-For.
    //
    // Used by:
    //   - PHPUnit — the Unit suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_client_ip_header_wins_over_the_forwarded_one(): void
    {
        $_SERVER['HTTP_CLIENT_IP'] = '203.0.113.9';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.4';

        $this->assertSame('203.0.113.9', SettingsController::getUserIP());
    }






    // -----------------------------------------------------------
    // test_the_cloudflare_header_wins_and_rewrites_the_globals
    // -----------------------------------------------------------
    //
    // CF-Connecting-IP beats every other source, and is copied
    // over the connection address and the Client-IP header —
    // whatever runs later in the same request sees it there.
    //
    // Used by:
    //   - PHPUnit — the Unit suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_cloudflare_header_wins_and_rewrites_the_globals(): void
    {
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '192.0.2.44';
        $_SERVER['HTTP_CLIENT_IP'] = '203.0.113.9';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.4';

        $this->assertSame('192.0.2.44', SettingsController::getUserIP());
        $this->assertSame('192.0.2.44', $_SERVER['REMOTE_ADDR']);
        $this->assertSame('192.0.2.44', $_SERVER['HTTP_CLIENT_IP']);
    }






    // -----------------------------------------------------------
    // test_malformed_headers_are_skipped
    // -----------------------------------------------------------
    //
    // A Client-IP or X-Forwarded-For value that is not an
    // address is passed over, down to the connection address.
    //
    // Used by:
    //   - PHPUnit — the Unit suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_malformed_headers_are_skipped(): void
    {
        $_SERVER['HTTP_CLIENT_IP'] = 'unknown';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '999.1.1.1';

        $this->assertSame('172.18.7.2', SettingsController::getUserIP());
    }






    // -----------------------------------------------------------
    // test_ipv6_addresses_are_accepted
    // -----------------------------------------------------------
    //
    // An IPv6 address in X-Forwarded-For counts as valid.
    //
    // Used by:
    //   - PHPUnit — the Unit suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_ipv6_addresses_are_accepted(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '2001:db8::7';

        $this->assertSame('2001:db8::7', SettingsController::getUserIP());
    }
}
