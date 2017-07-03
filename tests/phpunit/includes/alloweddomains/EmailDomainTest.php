<?php

namespace GoogleLogin\AllowedDomains;

class EmailDomainTest extends \MediaWikiTestCase {
	private static $preDefinedSuffixes = [
		'com',
		'us.co',
	];

	/**
	 * @var EmailDomain
	 */
	private $googleMail;

	/**
	 * @var EmailDomain
	 */
	private $subdomainMail;

	/**
	 * @var EmailDomain
	 */
	private $emptyMail;

	/**
	 * @var EmailDomain
	 */
	private $twoSuffixMail;

	public function setUp() {
		parent::setUp();
		$this->googleMail = new EmailDomain( 'test@gmail.com', false, self::$preDefinedSuffixes );
		$this->emptyMail = new EmailDomain( '', false, self::$preDefinedSuffixes );
		$this->subdomainMail = new EmailDomain( 'test@my.subdomain.com', false, self::$preDefinedSuffixes );
		$this->twoSuffixMail = new EmailDomain( 'test@my.subdomain.co.us', false, self::$preDefinedSuffixes );
	}

	public function testGetEMail() {
		$this->assertEquals( 'test@gmail.com', $this->googleMail->getEmail() );
		$this->assertEquals( '', $this->emptyMail->getEmail() );
		$this->assertEquals( 'test@my.subdomain.com', $this->subdomainMail->getEmail() );
		$this->assertEquals( 'test@my.subdomain.co.us', $this->twoSuffixMail->getEmail() );
	}

	public function testGetHost() {
		$this->assertEquals( '', $this->emptyMail->getHost() );
		$this->assertEquals( 'gmail.com', $this->googleMail->getHost() );
		$this->assertEquals( 'subdomain.com', $this->subdomainMail->getHost() );
		$this->assertEquals( 'subdomain.co.us', $this->twoSuffixMail->getHost() );
	}

	public function testGetHostStrict() {
		$emptyMailStrict = new EmailDomain( '', true, self::$preDefinedSuffixes );
		$googleMailStrict = new EmailDomain( 'test@gmail.com', true, self::$preDefinedSuffixes );
		$subdomainMailStrict = new EmailDomain( 'test@my.subdomain.com', true, self::$preDefinedSuffixes );
		$twoSuffixMailStrict = new EmailDomain( 'test@my.subdomain.co.us', true, self::$preDefinedSuffixes );
		$this->assertEquals( '', $emptyMailStrict->getHost() );
		$this->assertEquals( 'gmail.com', $googleMailStrict->getHost() );
		$this->assertEquals( 'my.subdomain.com', $subdomainMailStrict->getHost() );
		$this->assertEquals( 'my.subdomain.co.us', $twoSuffixMailStrict->getHost() );
	}

	public function testNoSuffixArray() {
		try {
			$this->assertNull( new EmailDomain( '' ) );
		} catch ( \UnexpectedValueException $e) {
			$this->assertInstanceOf( \UnexpectedValueException::class, $e );
		}
	}
}
