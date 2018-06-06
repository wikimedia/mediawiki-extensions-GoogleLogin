<?php

namespace GoogleLogin;

class GoogleLoginHooksTest extends \MediaWikiTestCase {

	/**
	 * @var \ApiModuleManager
	 */
	private $moduleManager;

	public function setup() {
		parent::setUp();
		$this->moduleManager = new \ApiModuleManager( new \ApiMain() );
	}

	/**
	 * @covers \GoogleLogin\GoogleLoginHooks::onApiMainModuleManager()
	 */
	public function testOnApiMainModuleManagerDefault() {
		$this->setMwGlobals( [
			'wgGLAllowedDomainsDB' => false,
		] );
		GoogleLoginHooks::onApiMainModuleManager( $this->moduleManager );
		$this->assertFalse( $this->moduleManager->isDefined( 'googleloginmanagealloweddomain' ) );
	}

	/**
	 * @covers \GoogleLogin\GoogleLoginHooks::onApiMainModuleManager()
	 */
	public function testOnApiMainModuleManagerNonDB() {
		$this->setMwGlobals( [
			'wgGLAllowedDomains' => [
				'test.com',
			],
			'wgGLAllowedDomainsDB' => false,
		] );
		GoogleLoginHooks::onApiMainModuleManager( $this->moduleManager );
		$this->assertFalse( $this->moduleManager->isDefined( 'googleloginmanagealloweddomain' ) );
	}

	/**
	 * @covers \GoogleLogin\GoogleLoginHooks::onApiMainModuleManager()
	 */
	public function testOnApiMainModuleManagerDB() {
		$this->setMwGlobals( [
			'wgGLAllowedDomainsDB' => true,
		] );
		GoogleLoginHooks::onApiMainModuleManager( $this->moduleManager );
		$this->assertTrue( $this->moduleManager->isDefined( 'googleloginmanagealloweddomain' ) );
	}

	/**
	 * @covers \GoogleLogin\GoogleLoginHooks::onApiMainModuleManager()
	 */
	public function testOnApiMainModuleManagerDBNonDB() {
		$this->setMwGlobals( [
			'wgGLAllowedDomains' => [
				'test.com',
			],
			'wgGLAllowedDomainsDB' => true,
		] );
		GoogleLoginHooks::onApiMainModuleManager( $this->moduleManager );
		$this->assertTrue( $this->moduleManager->isDefined( 'googleloginmanagealloweddomain' ) );
	}

	/**
	 * @covers \GoogleLogin\GoogleLoginHooks::onLoadExtensionSchemaUpdates()
	 */
	public function testOnLoadExtensionSchemaUpdatesAddsToShared() {
		$this->setMwGlobals( [
			'wgSharedDB' => true,
		] );
		$dbUpdaterMock = $this
			->getMockBuilder( \DatabaseUpdater::class )
			->disableOriginalConstructor()
			->getMock();
		$dbUpdaterMock->method( 'getDB' )->willReturn( wfGetDB( DB_REPLICA ) );
		$dbUpdaterMock
			->expects( $this->atLeastOnce() )
			->method( 'addExtensionUpdate' );

		GoogleLoginHooks::onLoadExtensionSchemaUpdates( $dbUpdaterMock );
	}

	/**
	 * @covers \GoogleLogin\GoogleLoginHooks::onLoadExtensionSchemaUpdates()
	 * @dataProvider provideAllowedDomainsConfig
	 */
	public function testOnLoadExtensionSchemaUpdatesAddsAllowdDomains( $isAllowedDomainsEnabled ) {
		$this->setMwGlobals( [
			'wgGLAllowedDomainsDB' => $isAllowedDomainsEnabled,
		] );
		$dbUpdaterMock = $this
			->getMockBuilder( \DatabaseUpdater::class )
			->disableOriginalConstructor()
			->getMock();
		$dbUpdaterMock->method( 'getDB' )->willReturn( wfGetDB( DB_REPLICA ) );
		$dbUpdaterMock
			->expects( $this->exactly( 2 ) )
			->method( 'addExtensionUpdate' )
			->with( $this->callback( function ( $arg ) {
				return in_array( $arg[1], [ 'user_google_user', 'googlelogin_allowed_domains' ] );
			} ) );

		$this->assertTrue( GoogleLoginHooks::onLoadExtensionSchemaUpdates( $dbUpdaterMock ) );
	}

	public function provideAllowedDomainsConfig() {
		return [
			[ true ],
			[ false ],
		];
	}
}
