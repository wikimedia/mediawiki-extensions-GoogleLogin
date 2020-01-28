<?php

namespace GoogleLogin;

use ApiMain;
use ApiModuleManager;
use GoogleLogin\Auth\GooglePrimaryAuthenticationProvider;
use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 */
class GoogleLoginHooksTest extends MediaWikiIntegrationTestCase {

	/**
	 * @var ApiModuleManager
	 */
	private $moduleManager;

	public function setUp() : void {
		parent::setUp();
		$this->moduleManager = new ApiModuleManager(
			new ApiMain(),
			MediaWikiServices::getInstance()->getObjectFactory()
		);
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
		$dbUpdaterMock =
			$this->getMockBuilder( \DatabaseUpdater::class )
				->disableOriginalConstructor()
				->getMock();
		$dbUpdaterMock->method( 'getDB' )->willReturn( wfGetDB( DB_REPLICA ) );
		$dbUpdaterMock->expects( $this->atLeastOnce() )->method( 'addExtensionUpdate' );

		GoogleLoginHooks::onLoadExtensionSchemaUpdates( $dbUpdaterMock );
	}

	/**
	 * @covers       \GoogleLogin\GoogleLoginHooks::onLoadExtensionSchemaUpdates()
	 * @dataProvider provideAllowedDomainsConfig
	 */
	public function testOnLoadExtensionSchemaUpdatesAddsAllowdDomains( $isAllowedDomainsEnabled ) {
		$this->setMwGlobals( [
			'wgGLAllowedDomainsDB' => $isAllowedDomainsEnabled,
		] );
		$dbUpdaterMock =
			$this->getMockBuilder( \DatabaseUpdater::class )
				->disableOriginalConstructor()
				->getMock();
		$dbUpdaterMock->method( 'getDB' )->willReturn( wfGetDB( DB_REPLICA ) );
		$dbUpdaterMock->expects( $this->exactly( 2 ) )
			->method( 'addExtensionUpdate' )
			->with( $this->callback( function ( $arg ) {
				return in_array( $arg[1], [ 'user_google_user', 'googlelogin_allowed_domains' ] );
			} ) );

		$this->assertTrue( GoogleLoginHooks::onLoadExtensionSchemaUpdates( $dbUpdaterMock ) );
	}

	/**
	 * @covers \GoogleLogin\GoogleLoginHooks::onSetup()
	 */
	public function testOnSetupAuthoritativeOtherProviders() {
		$this->setMwGlobals( [
			'wgGLAuthoritativeMode' => true,
			'wgAuthManagerConfig' => [
				'primaryauth' => [
					'AnyOtherProvider' => [],
					GooglePrimaryAuthenticationProvider::class => [
						'class' => GooglePrimaryAuthenticationProvider::class,
					],
				],
			],
		] );
		$this->expectException( ConfigurationError::class );

		GoogleLoginHooks::onSetup();
	}

	/**
	 * @covers \GoogleLogin\GoogleLoginHooks::onSetup()
	 */
	public function testOnSetupAuthoritativeNoOtherProviders() {
		$this->setMwGlobals( [
			'wgGLAuthoritativeMode' => true,
			'wgAuthManagerConfig' => [
				'primaryauth' => [
					GooglePrimaryAuthenticationProvider::class => [
						'class' => GooglePrimaryAuthenticationProvider::class,
					],
				],
			],
			'wgInvalidUsernameCharacters' => ':',
		] );

		try {
			$this->assertNull( GoogleLoginHooks::onSetup() );
		}
		catch ( ConfigurationError $exception ) {
			$this->fail( 'Exception should not be thrown' );
		}
	}

	/**
	 * @covers \GoogleLogin\GoogleLoginHooks::onSetup()
	 */
	public function testOnSetupNonAuthoritativeOtherProviders() {
		$this->setMwGlobals( [
			'wgGLAuthoritativeMode' => false,
			'wgAuthManagerConfig' => [
				'primaryauth' => [
					'AnyOtherProvider' => [],
					GooglePrimaryAuthenticationProvider::class => [
						'class' => GooglePrimaryAuthenticationProvider::class,
					],
				],
			],
		] );

		try {
			$this->assertNull( GoogleLoginHooks::onSetup() );
		}
		catch ( ConfigurationError $exception ) {
			$this->fail( 'Exception should not be thrown' );
		}
	}

	/**
	 * @covers \GoogleLogin\GoogleLoginHooks::onSetup()
	 */
	public function testOnSetupNonAuthoritativeAtDisallowedUserChar() {
		$this->setMwGlobals( [
			'wgGLAuthoritativeMode' => false,
			'wgAuthManagerConfig' => [
				'primaryauth' => [
					'AnyOtherProvider' => [],
					GooglePrimaryAuthenticationProvider::class => [
						'class' => GooglePrimaryAuthenticationProvider::class,
					],
				],
			],
			'wgInvalidUsernameCharacters' => '@',
		] );

		try {
			$this->assertNull( GoogleLoginHooks::onSetup() );
		}
		catch ( ConfigurationError $exception ) {
			$this->fail( 'Exception should not be thrown' );
		}
	}

	/**
	 * @covers \GoogleLogin\GoogleLoginHooks::onSetup()
	 */
	public function testOnSetupAuthoritativeAtDisallowedUserChar() {
		$this->setMwGlobals( [
			'wgGLAuthoritativeMode' => true,
			'wgAuthManagerConfig' => [
				'primaryauth' => [
					GooglePrimaryAuthenticationProvider::class => [
						'class' => GooglePrimaryAuthenticationProvider::class,
					],
				],
			],
			'wgInvalidUsernameCharacters' => '@',
		] );

		$this->expectException( ConfigurationError::class );

		GoogleLoginHooks::onSetup();
	}

	/**
	 * @covers \GoogleLogin\GoogleLoginHooks::onSetup()
	 */
	public function testOnSetupAuthoritativeNoAtDisallowedUserChar() {
		$this->setMwGlobals( [
			'wgGLAuthoritativeMode' => true,
			'wgAuthManagerConfig' => [
				'primaryauth' => [
					GooglePrimaryAuthenticationProvider::class => [
						'class' => GooglePrimaryAuthenticationProvider::class,
					],
				],
			],
			'wgInvalidUsernameCharacters' => ':',
		] );

		try {
			$this->assertNull( GoogleLoginHooks::onSetup() );
		}
		catch ( ConfigurationError $exception ) {
			$this->fail( 'Exception should not be thrown' );
		}
	}

	public function provideAllowedDomainsConfig() {
		return [
			[ true ],
			[ false ],
		];
	}
}
