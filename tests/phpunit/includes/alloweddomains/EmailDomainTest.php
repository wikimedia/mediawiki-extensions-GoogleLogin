<?php

namespace GoogleLogin\AllowedDomains;

class EmailDomainTest extends \MediaWikiTestCase {
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
		$this->googleMail = new EmailDomain( 'test@gmail.com', false );
		$this->emptyMail = new EmailDomain( '', false );
		$this->subdomainMail = new EmailDomain( 'test@my.subdomain.com', false );
		$this->twoSuffixMail = new EmailDomain( 'test@my.subdomain.co.us', false );
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
		$emptyMailStrict = new EmailDomain( '', true );
		$googleMailStrict = new EmailDomain( 'test@gmail.com', true );
		$subdomainMailStrict = new EmailDomain( 'test@my.subdomain.com', true );
		$twoSuffixMailStrict = new EmailDomain( 'test@my.subdomain.co.us', true );
		$this->assertEquals( '', $emptyMailStrict->getHost() );
		$this->assertEquals( 'gmail.com', $googleMailStrict->getHost() );
		$this->assertEquals( 'my.subdomain.com', $subdomainMailStrict->getHost() );
		$this->assertEquals( 'my.subdomain.co.us', $twoSuffixMailStrict->getHost() );
	}
}
