<?php

namespace GoogleLogin\AllowedDomains;

use MediaWikiUnitTestCase;

class EmailDomainTest extends MediaWikiUnitTestCase {
	private static $preDefinedSuffixes = [
		'com',
		'us.co',
	];
	/** @var PublicSuffixLookup */
	private static $publicSuffixLookup;

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

	public function setUp(): void {
		parent::setUp();
		if ( self::$publicSuffixLookup === null ) {
			self::$publicSuffixLookup = PublicSuffixLookup::fromSuffixList( self::$preDefinedSuffixes );
		}
		$this->googleMail = new EmailDomain( 'test@gmail.com', self::$publicSuffixLookup, false );
		$this->emptyMail = new EmailDomain( '', self::$publicSuffixLookup, false );
		$this->subdomainMail =
			new EmailDomain( 'test@my.subdomain.com', self::$publicSuffixLookup, false );
		$this->twoSuffixMail =
			new EmailDomain( 'test@my.subdomain.co.us', self::$publicSuffixLookup, false );
	}

	/**
	 * @covers \GoogleLogin\AllowedDomains\EmailDomain::getEmail()
	 */
	public function testGetEMail() {
		$this->assertEquals( 'test@gmail.com', $this->googleMail->getEmail() );
		$this->assertSame( '', $this->emptyMail->getEmail() );
		$this->assertEquals( 'test@my.subdomain.com', $this->subdomainMail->getEmail() );
		$this->assertEquals( 'test@my.subdomain.co.us', $this->twoSuffixMail->getEmail() );
	}

	/**
	 * @covers \GoogleLogin\AllowedDomains\EmailDomain::getHost()
	 */
	public function testGetHost() {
		$this->assertSame( '', $this->emptyMail->getHost() );
		$this->assertEquals( 'gmail.com', $this->googleMail->getHost() );
		$this->assertEquals( 'subdomain.com', $this->subdomainMail->getHost() );
		$this->assertEquals( 'subdomain.co.us', $this->twoSuffixMail->getHost() );
	}

	/**
	 * @covers \GoogleLogin\AllowedDomains\EmailDomain::getHost()
	 */
	public function testGetHostStrict() {
		$emptyMailStrict = new EmailDomain( '', self::$publicSuffixLookup, true );
		$googleMailStrict = new EmailDomain( 'test@gmail.com', self::$publicSuffixLookup, true );
		$subdomainMailStrict =
			new EmailDomain( 'test@my.subdomain.com', self::$publicSuffixLookup, true );
		$twoSuffixMailStrict =
			new EmailDomain( 'test@my.subdomain.co.us', self::$publicSuffixLookup, true );
		$this->assertSame( '', $emptyMailStrict->getHost() );
		$this->assertEquals( 'gmail.com', $googleMailStrict->getHost() );
		$this->assertEquals( 'my.subdomain.com', $subdomainMailStrict->getHost() );
		$this->assertEquals( 'my.subdomain.co.us', $twoSuffixMailStrict->getHost() );
	}

	/**
	 * @covers \GoogleLogin\AllowedDomains\PublicSuffixArrayPath::fromDirectory
	 */
	public function testBuildPublicSuffixArrayFilePathAddsTrailingSlash() {
		$this->assertSame(
			'/tmp/publicSuffixArray.php',
			PublicSuffixArrayPath::fromDirectory( '/tmp' )
		);
		$this->assertSame(
			'/tmp/publicSuffixArray.php',
			PublicSuffixArrayPath::fromDirectory( '/tmp/' )
		);
	}

	/**
	 * @covers \GoogleLogin\AllowedDomains\PublicSuffixArrayPath::fromDirectory
	 */
	public function testBuildPublicSuffixArrayFilePathFallback() {
		$expected = realpath( __DIR__ . '/../../../../../' ) . '/publicSuffixArray.php';
		$actual = realpath( dirname( PublicSuffixArrayPath::fromDirectory( '' ) ) )
			. '/publicSuffixArray.php';

		$this->assertSame( $expected, $actual );
	}

}
