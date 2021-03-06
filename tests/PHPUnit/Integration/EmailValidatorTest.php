<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Tests\Integration;

use Piwik\Http;
use Piwik\Piwik;

/**
 * @group Core
 */
class EmailValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected function isValid($email)
    {
        return Piwik::isValidEmailString($email);
    }

    private function getAllTlds()
    {
        /** @var array $response */
        $response = \Piwik\Http::sendHttpRequest("http://data.iana.org/TLD/tlds-alpha-by-domain.txt", 30, null, null, null, null, null, true);

        $this->assertEquals("200", $response['status']);

        $tlds = explode("\n", $response['data']);
        foreach ($tlds as $key => $tld) {
            if (strpos($tld, '#') !== false || $tld == "") {
                unset($tlds[$key]);
            }
        }
        return $tlds;
    }

    private function skipTestIfIdnNotAvailable()
    {
        if (!function_exists('idn_to_utf8')) {
            $this->markTestSkipped("Couldn't get TLD list");
        }
    }

    public function test_allCurrentTlds(){

        $this->skipTestIfIdnNotAvailable();

        $tlds = $this->getAllTlds();
        if (count($tlds) === 0) {
            $this->markTestSkipped("Couldn't get TLD list");
        }

        foreach ($tlds as $key => $tld) {
            if (strpos(mb_strtolower($tld), 'xn--') !== 0) {
                $tld = mb_strtolower($tld);
            }
            $email = 'test@example.' . idn_to_utf8($tld);
            $this->assertTrue(
                $this->isValid($email),
                "email $email is not valid, but expected to be valid. Add this domain extension to  libs/Zend/Validate/Hostname.php"
            );
        }
    }

    public function test_invalidTld(){
        $this->skipTestIfIdnNotAvailable();

        $tlds = [
            strval(bin2hex(openssl_random_pseudo_bytes(64))), //generates 128 bit length string
            '-tld-cannot-start-from-hypen',
            'ąęśćżźł-there-is-no-such-idn',
            'xn--fd67as67fdsa', //no such idn punycode
            '!@#-inavlid-chars-in-tld',
            'no spaces in tld allowed',
            'no--double--hypens--allowed'
        ];
        if (count($tlds) === 0) {
            $this->markTestSkipped("Couldn't get TLD list");
        }

        foreach ($tlds as $key => $tld) {
            if (strpos(mb_strtolower($tld), 'xn--') !== 0) {
                $tld = mb_strtolower($tld);
            }
            $this->assertFalse(
                $this->isValid('test@example.' . idn_to_utf8($tld))
            );
        }
    }

    public function test_isValid_validStandard()
    {
        $this->assertTrue($this->isValid('test@example.com'));
    }

    public function test_isValid_unknownTld()
    {
        $this->assertTrue($this->isValid('test@example.unknown'));
    }

    public function test_isValid_validUpperCaseLocalPart()
    {
        $this->assertTrue($this->isValid('TEST@example.com'));
    }

    public function test_isValid_validNumericLocalPart()
    {
        $this->assertTrue($this->isValid('1234567890@example.com'));
    }

    public function test_isValid_validTaggedLocalPart()
    {
        $this->assertTrue($this->isValid('test+test@example.com'));
    }

    public function test_isValid_validQmailLocalPart()
    {
        $this->assertTrue($this->isValid('test-test@example.com'));
    }

    public function test_isValid_validUnusualCharactersInLocalPart()
    {
        $this->assertTrue($this->isValid('t*est@example.com'));
        $this->assertTrue($this->isValid('+1~1+@example.com'));
        $this->assertTrue($this->isValid('{_test_}@example.com'));
    }

    public function test_isValid_validQuotedLocalPart()
    {
        $this->assertTrue($this->isValid('"[[ test ]]"@example.com'));
    }

    public function test_isValid_validAtomisedLocalPart()
    {
        $this->assertTrue($this->isValid('test.test@example.com'));
    }

    public function test_isValid_validQuotedAtLocalPart()
    {
        $this->assertTrue($this->isValid('"test@test"@example.com'));
    }

    public function test_isValid_validMultipleLabelDomain()
    {
        $this->assertTrue($this->isValid('test@example.example.com'));
        $this->assertTrue($this->isValid('test@example.example.example.com'));
    }

    public function test_isValid_invalidTooLong()
    {
        $this->assertFalse($this->isValid('12345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345@example.com'));
    }

    public function test_isValid_invalidTooShort()
    {
        $this->assertFalse($this->isValid('@a'));
    }

    public function test_isValid_invalidNoAtSymbol()
    {
        $this->assertFalse($this->isValid('test.example.com'));
    }

    public function test_isValid_invalidBlankAtomInLocalPart()
    {
        $this->assertFalse($this->isValid('test.@example.com'));
        $this->assertFalse($this->isValid('test..test@example.com'));
        $this->assertFalse($this->isValid('.test@example.com'));
    }

    public function test_isValid_invalidMultipleAtSymbols()
    {
        $this->assertFalse($this->isValid('test@test@example.com'));
        $this->assertFalse($this->isValid('test@@example.com'));
    }

    public function test_isValid_invalidInvalidCharactersInLocalPart()
    {
        $this->assertFalse($this->isValid('-- test --@example.com'));
        $this->assertFalse($this->isValid('[test]@example.com'));
        $this->assertFalse($this->isValid('"test"test"@example.com'));
        $this->assertFalse($this->isValid('()[]\;:,<>@example.com'));
    }

    public function test_isValid_invalidDomainLabelTooShort()
    {
        $this->assertFalse($this->isValid('test@.'));
        $this->assertFalse($this->isValid('test@example.'));
        $this->assertFalse($this->isValid('test@.org'));
    }

    public function test_isValid_invalidLocalPartTooLong()
    {
        $this->assertFalse($this->isValid('12345678901234567890123456789012345678901234567890123456789012345@example.com')); // 64 characters is maximum length for local part
    }

    public function test_isValid_invalidDomainLabelTooLong()
    {
        $this->assertFalse($this->isValid('test@123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012.com')); // 255 characters is maximum length for domain. This is 256.
    }

    public function test_isValid_invalidTooFewLabelsInDomain()
    {
        $this->assertFalse($this->isValid('test@example'));
    }

    public function test_isValid_invalidUnpartneredSquareBracketIp()
    {
        $this->assertFalse($this->isValid('test@[123.123.123.123'));
        $this->assertFalse($this->isValid('test@123.123.123.123]'));
    }
}
