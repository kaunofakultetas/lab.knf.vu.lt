<?php

// -----------------------------------------------------------
//  [*] SamlMessagesTest — the VU sign-on's messages, checked
//      for real
//
//  The SSO tests of the Feature suite stand SimpleSAMLphp in
//  with a mock; these run the real libraries the image ships
//  — SimpleSAMLphp's SP code, saml2 and xmlseclibs — on
//  messages signed with a throwaway key standing in for the
//  VU IdP. A login response (HTTP-POST) goes through the
//  checks a VU login goes through, a logout response
//  (HTTP-Redirect, its signature in the address) through the
//  signature check a sign-out goes through, and the SP builds
//  its own login and logout requests. A genuine message
//  passes; a forged one — changed after signing, unsigned,
//  signed by another key, meant for another SP — is refused.
//  The libraries are held to versions free of the published
//  advisories, among them signature bypasses.
//
//  Used by:
//    - PHPUnit — the Unit suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Unit;

use Composer\InstalledVersions;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use SAML2\Assertion;
use SAML2\Constants;
use SAML2\DOMDocumentFactory;
use SAML2\HTTPRedirect;
use SAML2\LogoutResponse;
use SAML2\Response;
use SAML2\XML\saml\Issuer;
use SAML2\XML\saml\NameID;
use SAML2\XML\saml\SubjectConfirmation;
use SAML2\XML\saml\SubjectConfirmationData;
use SimpleSAML\Configuration;
use SimpleSAML\Logger;
use SimpleSAML\Module\saml\Message;








// -----------------------------------------------------------
// SamlMessagesTest
// -----------------------------------------------------------
//
// Every test runs in a process of its own: SimpleSAMLphp
// keeps its configuration in static state, and the SSO
// tests of the Feature suite replace its classes with
// mocks, which a process that loaded the real ones could
// not do.
//
//   setUp           — the SP's configuration and two keys
//   keypair         — a throwaway key and its certificate
//   issuer          — an Issuer element
//   loginResponse   — a signed (or unsigned) login response
//   processLogin    — the SP's checks of a login response
//   logoutQuery     — a signed logout response's address
//   receiveLogout   — the SP's check of a logout response
//
// Used by:
//   - PHPUnit — the Unit suite (runTests.sh)
// -----------------------------------------------------------

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SamlMessagesTest extends TestCase
{

    // The entity ids of the lab's SP and of the VU IdP, and the
    // id of the login request the responses answer
    private const SP = 'https://lab.knf.vu.lt';
    private const IDP = 'https://sso.vu.lt/SSO/saml2/idp/metadata.php';
    private const REQUEST = '_lab-test-request-0001';

    // The IdP's key and certificate, a stranger's key and
    // certificate, and the two sides' metadata
    private XMLSecurityKey $idpKey;
    private string $idpCertificate;
    private XMLSecurityKey $otherKey;
    private string $otherCertificate;
    private Configuration $spMetadata;
    private Configuration $idpMetadata;






    // -----------------------------------------------------------
    // setUp
    // -----------------------------------------------------------
    //
    // Hands SimpleSAMLphp a configuration of its own, logging
    // nothing short of an emergency, and the request of the
    // SP's assertion consumer, then makes the throwaway IdP
    // key, whose certificate is the IdP's in the metadata, and
    // a stranger's key.
    //
    // Used by:
    //   - PHPUnit, before every test
    // -----------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER = array_merge($_SERVER, [
            'HTTP_HOST' => 'lab.knf.vu.lt',
            'SERVER_NAME' => 'lab.knf.vu.lt',
            'HTTPS' => 'on',
            'SERVER_PORT' => '443',
            'REQUEST_URI' => '/simplesaml/module.php/saml/sp/saml2-acs.php/default-sp',
            'SCRIPT_NAME' => '/simplesaml/module.php',
            'PATH_INFO' => '/saml/sp/saml2-acs.php/default-sp',
            'REQUEST_METHOD' => 'POST',
        ]);
        Configuration::setPreLoadedConfig(Configuration::loadFromArray([
            'baseurlpath' => 'https://lab.knf.vu.lt/simplesaml/',
            'secretsalt' => bin2hex(random_bytes(16)),
            'logging.handler' => 'errorlog',
            'logging.level' => Logger::EMERG,
            'store.type' => 'phpsession',
        ]), 'config.php');

        [$this->idpKey, $this->idpCertificate] = $this->keypair('test-idp');
        [$this->otherKey, $this->otherCertificate] = $this->keypair('stranger');
        $this->spMetadata = Configuration::loadFromArray(['entityid' => self::SP, 'entityID' => self::SP]);
        $this->idpMetadata = Configuration::loadFromArray([
            'entityid' => self::IDP,
            'assertion.encryption' => false,
            'keys' => [[
                'signing' => true,
                'encryption' => false,
                'type' => 'X509Certificate',
                'X509Certificate' => preg_replace('/-----[^-]+-----|\s/', '', $this->idpCertificate),
            ]],
        ]);
    }






    // -----------------------------------------------------------
    // keypair
    // -----------------------------------------------------------
    //
    // A new 2048-bit RSA key, as a signing key for SHA-256
    // signatures, and its self-signed certificate in PEM.
    //
    // Used by:
    //   - setUp (above)
    // -----------------------------------------------------------

    private function keypair(string $name): array
    {
        $private = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new(['commonName' => $name], $private, ['digest_alg' => 'sha256']);
        openssl_x509_export(openssl_csr_sign($csr, null, $private, 1, ['digest_alg' => 'sha256']), $certificate);
        openssl_pkey_export($private, $pem);

        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
        $key->loadKey($pem);

        return [$key, $certificate];
    }






    // -----------------------------------------------------------
    // issuer
    // -----------------------------------------------------------
    //
    // An Issuer element naming the entity given.
    //
    // Used by:
    //   - loginResponse and logoutQuery (below)
    // -----------------------------------------------------------

    private function issuer(string $entity): Issuer
    {
        $issuer = new Issuer();
        $issuer->setValue($entity);

        return $issuer;
    }






    // -----------------------------------------------------------
    // loginResponse
    // -----------------------------------------------------------
    //
    // The XML of a login response the IdP would post in answer
    // to a login request — the request's id in the response
    // and in the confirmation, as SimpleSAMLphp 2.5 requires
    // of a solicited response: one assertion for a VU student,
    // valid for five minutes, for the audience given, with a
    // bearer confirmation and two attributes — signed with the
    // key given, or unsigned when none is.
    //
    // Used by:
    //   - the login tests below
    // -----------------------------------------------------------

    private function loginResponse(?XMLSecurityKey $key, ?string $certificate, string $audience = self::SP, ?string $request = self::REQUEST): string
    {
        $assertion = new Assertion();
        $assertion->setIssuer($this->issuer(self::IDP));
        $nameId = new NameID();
        $nameId->setValue('s1234567');
        $nameId->setFormat(Constants::NAMEID_TRANSIENT);
        $assertion->setNameId($nameId);
        $assertion->setNotBefore(time() - 30);
        $assertion->setNotOnOrAfter(time() + 300);
        $assertion->setValidAudiences([$audience]);
        $data = new SubjectConfirmationData();
        $data->setNotOnOrAfter(time() + 300);
        $data->setInResponseTo($request);
        $confirmation = new SubjectConfirmation();
        $confirmation->setMethod(Constants::CM_BEARER);
        $confirmation->setSubjectConfirmationData($data);
        $assertion->setSubjectConfirmation([$confirmation]);
        $assertion->setAttributes(['uid' => ['s1234567'], 'mail' => ['student@stud.vu.lt']]);
        if ($key !== null) {
            $assertion->setSignatureKey($key);
            $assertion->setCertificates([$certificate]);
        }

        $response = new Response();
        $response->setIssuer($this->issuer(self::IDP));
        $response->setInResponseTo($request);
        $response->setAssertions([$assertion]);

        return $response->toSignedXML()->ownerDocument->saveXML();
    }






    // -----------------------------------------------------------
    // processLogin
    // -----------------------------------------------------------
    //
    // Puts the XML through SimpleSAMLphp's processResponse as
    // the SP's assertion consumer does for an answer to its
    // own login request — the request's id and the IdP
    // expected — and gives back the student's id and mail of
    // the assertion it lets through; a refusal is the
    // exception it throws.
    //
    // Used by:
    //   - the login tests below
    // -----------------------------------------------------------

    private function processLogin(string $xml): string
    {
        $response = new Response(DOMDocumentFactory::fromString($xml)->documentElement);
        $assertion = Message::processResponse($this->spMetadata, $this->idpMetadata, $response, self::REQUEST, false, self::IDP)[0];

        return $assertion->getNameId()->getValue() . ' ' . $assertion->getAttributes()['mail'][0];
    }






    // -----------------------------------------------------------
    // logoutQuery
    // -----------------------------------------------------------
    //
    // The query string of the address the IdP sends a
    // successful logout response back by (HTTP-Redirect), its
    // signature made with the key given.
    //
    // Used by:
    //   - the logout tests below
    // -----------------------------------------------------------

    private function logoutQuery(XMLSecurityKey $key): string
    {
        $response = new LogoutResponse();
        $response->setIssuer($this->issuer(self::IDP));
        $response->setDestination('https://lab.knf.vu.lt/simplesaml/module.php/saml/sp/saml2-logout.php/default-sp');
        $response->setInResponseTo('_' . bin2hex(random_bytes(16)));
        $response->setStatus(['Code' => Constants::STATUS_SUCCESS]);
        $response->setSignatureKey($key);

        return parse_url((new HTTPRedirect())->getRedirectURL($response), PHP_URL_QUERY);
    }






    // -----------------------------------------------------------
    // receiveLogout
    // -----------------------------------------------------------
    //
    // Receives the query string as the SP's logout endpoint
    // does and checks its signature against the IdP's
    // certificate (Message::checkSign); a refusal is the
    // exception either throws.
    //
    // Used by:
    //   - the logout tests below
    // -----------------------------------------------------------

    private function receiveLogout(string $query): bool
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['QUERY_STRING'] = $query;
        parse_str($query, $_GET);

        return Message::checkSign($this->idpMetadata, (new HTTPRedirect())->receive());
    }






    // -----------------------------------------------------------
    // test_the_sso_libraries_carry_no_published_advisory
    // -----------------------------------------------------------
    //
    // SimpleSAMLphp is 2.5.3.1 or later, its saml2 6.2.1 or
    // later, the legacy saml2 it still builds its messages
    // with 4.20.3 or later and xmlseclibs 3.1.5 or later: the
    // versions before carry published advisories, among them a
    // canonicalization error that lets a changed message pass
    // its signature check (CVE-2025-66578), wrong signature
    // checks on the HTTP-Redirect binding (CVE-2025-27773) and
    // a cross-IdP authentication bypass (CVE-2026-49283).
    //
    // Used by:
    //   - PHPUnit — the Unit suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_sso_libraries_carry_no_published_advisory(): void
    {
        foreach ([
            'simplesamlphp/simplesamlphp' => '2.5.3.1',
            'simplesamlphp/saml2' => '6.2.1',
            'simplesamlphp/saml2-legacy' => '4.20.3',
            'robrichards/xmlseclibs' => '3.1.5',
        ] as $package => $fixed) {
            $version = InstalledVersions::getVersion($package);
            $this->assertTrue(version_compare($version, $fixed, '>='), "{$package} {$version}");
        }
    }






    // -----------------------------------------------------------
    // test_a_login_signed_by_the_idp_is_accepted
    // -----------------------------------------------------------
    //
    // A login response whose assertion the IdP signed passes
    // every check and hands over the student's id and mail.
    //
    // Used by:
    //   - PHPUnit — the Unit suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_login_signed_by_the_idp_is_accepted(): void
    {
        $xml = $this->loginResponse($this->idpKey, $this->idpCertificate);

        $this->assertSame('s1234567 student@stud.vu.lt', $this->processLogin($xml));
    }






    // -----------------------------------------------------------
    // test_a_login_changed_after_signing_is_refused
    // -----------------------------------------------------------
    //
    // A signed login response whose student id or mail was
    // changed afterwards is refused: the signature no longer
    // matches what it signed.
    //
    // Used by:
    //   - PHPUnit — the Unit suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_login_changed_after_signing_is_refused(): void
    {
        $xml = $this->loginResponse($this->idpKey, $this->idpCertificate);

        foreach ([['s1234567', 's7654321'], ['student@stud.vu.lt', 'admin@vu.lt']] as [$from, $to]) {
            $refusal = null;
            try {
                $this->processLogin(str_replace($from, $to, $xml));
            } catch (\Exception $e) {
                $refusal = $e;
            }
            $this->assertNotNull($refusal, "A login with {$to} instead of {$from} was accepted");
            $this->assertStringContainsString('validation failed', $refusal->getMessage());
        }
    }






    // -----------------------------------------------------------
    // test_an_unsigned_login_is_refused
    // -----------------------------------------------------------
    //
    // A login response with no signature at all is refused.
    //
    // Used by:
    //   - PHPUnit — the Unit suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_an_unsigned_login_is_refused(): void
    {
        $this->expectExceptionMessage('Neither the assertion nor the response was signed.');

        $this->processLogin($this->loginResponse(null, null));
    }






    // -----------------------------------------------------------
    // test_a_login_signed_by_another_key_is_refused
    // -----------------------------------------------------------
    //
    // A login response signed by a key other than the IdP's —
    // its own certificate carried along — is refused as a
    // signature that does not validate: only the certificate
    // in the IdP's metadata counts.
    //
    // Used by:
    //   - PHPUnit — the Unit suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_login_signed_by_another_key_is_refused(): void
    {
        $this->expectExceptionMessageMatches('/validate (certificate )?signature/i');

        $this->processLogin($this->loginResponse($this->otherKey, $this->otherCertificate));
    }






    // -----------------------------------------------------------
    // test_a_login_for_another_service_is_refused
    // -----------------------------------------------------------
    //
    // A genuine login response the IdP issued for another
    // service provider is refused by the lab.
    //
    // Used by:
    //   - PHPUnit — the Unit suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_login_for_another_service_is_refused(): void
    {
        $this->expectExceptionMessage('is not a valid audience for the assertion');

        $this->processLogin($this->loginResponse($this->idpKey, $this->idpCertificate, 'https://other.example'));
    }






    // -----------------------------------------------------------
    // test_a_logout_signed_by_the_idp_is_accepted
    // -----------------------------------------------------------
    //
    // A logout response the IdP signed in its redirect address
    // passes the signature check.
    //
    // Used by:
    //   - PHPUnit — the Unit suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_logout_signed_by_the_idp_is_accepted(): void
    {
        $this->assertTrue($this->receiveLogout($this->logoutQuery($this->idpKey)));
    }






    // -----------------------------------------------------------
    // test_a_logout_with_a_bad_signature_is_refused
    // -----------------------------------------------------------
    //
    // A logout response whose signature was altered, made by
    // another key, made with its algorithm swapped afterwards
    // or left out is refused.
    //
    // Used by:
    //   - PHPUnit — the Unit suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_logout_with_a_bad_signature_is_refused(): void
    {
        parse_str($this->logoutQuery($this->idpKey), $genuine);
        $altered = array_merge($genuine, ['Signature' => base64_encode(strrev(base64_decode($genuine['Signature'])))]);
        $swapped = array_merge($genuine, ['SigAlg' => XMLSecurityKey::RSA_SHA1]);
        $unsigned = array_diff_key($genuine, ['Signature' => 1, 'SigAlg' => 1]);
        $forgeries = [
            'altered' => http_build_query($altered),
            'another key' => $this->logoutQuery($this->otherKey),
            'algorithm swapped' => http_build_query($swapped),
            'unsigned' => http_build_query($unsigned),
        ];

        foreach ($forgeries as $name => $query) {
            try {
                $accepted = $this->receiveLogout($query);
            } catch (\Exception $refusal) {
                $accepted = false;
            }
            $this->assertFalse($accepted, "A logout response with its signature {$name} was accepted");
        }
    }






    // -----------------------------------------------------------
    // test_the_sp_builds_its_login_and_logout_requests
    // -----------------------------------------------------------
    //
    // The SP builds the login and logout requests it sends to
    // the IdP and turns each into a redirect address.
    //
    // Used by:
    //   - PHPUnit — the Unit suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_sp_builds_its_login_and_logout_requests(): void
    {
        $login = Message::buildAuthnRequest($this->spMetadata, $this->idpMetadata);
        $login->setDestination('https://sso.vu.lt/SSO/saml2/idp/SSOService.php');
        $logout = Message::buildLogoutRequest($this->spMetadata, $this->idpMetadata);
        $logout->setDestination('https://sso.vu.lt/SSO/saml2/idp/SingleLogoutService.php');
        $nameId = new NameID();
        $nameId->setValue('s1234567');
        $logout->setNameId($nameId);

        $this->assertStringStartsWith('https://sso.vu.lt/SSO/saml2/idp/SSOService.php?SAMLRequest=', (new HTTPRedirect())->getRedirectURL($login));
        $this->assertStringStartsWith('https://sso.vu.lt/SSO/saml2/idp/SingleLogoutService.php?SAMLRequest=', (new HTTPRedirect())->getRedirectURL($logout));
    }






    // -----------------------------------------------------------
    // test_a_message_with_a_doctype_is_refused
    // -----------------------------------------------------------
    //
    // A message carrying a DOCTYPE — the way an external
    // entity would read a file of the server into it — is
    // refused before it is parsed.
    //
    // Used by:
    //   - PHPUnit — the Unit suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_message_with_a_doctype_is_refused(): void
    {
        $this->expectExceptionMessage('Dangerous XML detected');

        DOMDocumentFactory::fromString('<?xml version="1.0"?><!DOCTYPE r [<!ENTITY e SYSTEM "file:///etc/passwd">]><r>&e;</r>');
    }






    // -----------------------------------------------------------
    // test_a_login_answering_another_request_is_refused
    // -----------------------------------------------------------
    //
    // A genuine login response that answers a request the SP
    // did not send in this login — one taken from another
    // login and played back — is refused.
    //
    // Used by:
    //   - PHPUnit — the Unit suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_login_answering_another_request_is_refused(): void
    {
        $this->expectExceptionMessage('does not match expected request ID');

        $this->processLogin($this->loginResponse($this->idpKey, $this->idpCertificate, self::SP, '_another-request'));
    }






    // -----------------------------------------------------------
    // test_a_login_without_its_request_id_is_refused
    // -----------------------------------------------------------
    //
    // A genuine login response that names no request at all is
    // refused where the SP waits for the answer to its own
    // request.
    //
    // Used by:
    //   - PHPUnit — the Unit suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_login_without_its_request_id_is_refused(): void
    {
        $this->expectExceptionMessage('Solicited Response must contain InResponseTo.');

        $this->processLogin($this->loginResponse($this->idpKey, $this->idpCertificate, self::SP, null));
    }
}
