<?php

namespace GoogleLogin;

use ApiMain;
use ApiModuleManager;
use GoogleLogin\Auth\GooglePrimaryAuthenticationProvider;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Settings\SettingsBuilder;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 */
class GoogleLoginHooksTest extends MediaWikiIntegrationTestCase {

	/**
	 * @var ApiModuleManager
	 */
	private $moduleManager;

	public function setUp(): void {
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
			'GLAllowedDomainsDB' => false,
		] );
		GoogleLoginHooks::onApiMainModuleManager( $this->moduleManager );
		$this->assertFalse( $this->moduleManager->isDefined( 'googleloginmanagealloweddomain' ) );
	}

	/**
	 * @covers \GoogleLogin\GoogleLoginHooks::onApiMainModuleManager()
	 */
	public function testOnApiMainModuleManagerNonDB() {
		$this->overrideConfigValues( [
			'GLAllowedDomains' => [
				'test.com',
			],
			'GLAllowedDomainsDB' => false,
		] );
		GoogleLoginHooks::onApiMainModuleManager( $this->moduleManager );
		$this->assertFalse( $this->moduleManager->isDefined( 'googleloginmanagealloweddomain' ) );
	}

	/**
	 * @covers \GoogleLogin\GoogleLoginHooks::onApiMainModuleManager()
	 */
	public function testOnApiMainModuleManagerDB() {
		$this->overrideConfigValues( [
			'GLAllowedDomainsDB' => true,
		] );
		GoogleLoginHooks::onApiMainModuleManager( $this->moduleManager );
		$this->assertTrue( $this->moduleManager->isDefined( 'googleloginmanagealloweddomain' ) );
	}

	/**
	 * @covers \GoogleLogin\GoogleLoginHooks::onApiMainModuleManager()
	 */
	public function testOnApiMainModuleManagerDBNonDB() {
		$this->overrideConfigValues( [
			'GLAllowedDomains' => [
				'test.com',
			],
			'GLAllowedDomainsDB' => true,
		] );
		GoogleLoginHooks::onApiMainModuleManager( $this->moduleManager );
		$this->assertTrue( $this->moduleManager->isDefined( 'googleloginmanagealloweddomain' ) );
	}

	/**
	 * @covers \GoogleLogin\GoogleLoginHooks::onLoadExtensionSchemaUpdates()
	 */
	public function testOnLoadExtensionSchemaUpdatesAddsToShared() {
		$this->overrideConfigValues( [
			MainConfigNames::SharedDB => true,
		] );
		$dbUpdaterMock =
			$this->getMockBuilder( \DatabaseUpdater::class )
				->disableOriginalConstructor()
				->getMock();
		$dbUpdaterMock->method( 'getDB' )
			->willReturn( MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase() );
		$dbUpdaterMock->expects( $this->atLeastOnce() )->method( 'addExtensionUpdate' );

		GoogleLoginHooks::onLoadExtensionSchemaUpdates( $dbUpdaterMock );
	}

	/**
	 * @covers       \GoogleLogin\GoogleLoginHooks::onLoadExtensionSchemaUpdates()
	 * @dataProvider provideAllowedDomainsConfig
	 */
	public function testOnLoadExtensionSchemaUpdatesAddsAllowdDomains( $isAllowedDomainsEnabled ) {
		$this->overrideConfigValues( [
			'GLAllowedDomainsDB' => $isAllowedDomainsEnabled,
		] );
		$dbUpdaterMock =
			$this->getMockBuilder( \DatabaseUpdater::class )
				->disableOriginalConstructor()
				->getMock();
		$dbUpdaterMock->method( 'getDB' )
			->willReturn( MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase() );
		$dbUpdaterMock->expects( $this->exactly( 2 ) )
			->method( 'addExtensionUpdate' )
			->with( $this->callback( static function ( $arg ) {
				return in_array( $arg[1], [ 'user_google_user', 'googlelogin_allowed_domains' ] );
			} ) );

		$this->assertTrue( GoogleLoginHooks::onLoadExtensionSchemaUpdates( $dbUpdaterMock ) );
	}

	/**
	 * @covers \GoogleLogin\GoogleLoginHooks::onSetup()
	 */
	public function testOnSetupAuthoritativeOtherProviders() {
		$this->overrideConfigValues( [
			'GLAuthoritativeMode' => true,
			 MainConfigNames::AuthManagerConfig => [
				'primaryauth' => [
					'AnyOtherProvider' => [],
					GooglePrimaryAuthenticationProvider::class => [
						'class' => GooglePrimaryAuthenticationProvider::class,
					],
				],
			],
		] );
		$this->expectException( ConfigurationError::class );

		GoogleLoginHooks::onSetup( [], SettingsBuilder::getInstance() );
	}

	/**
	 * @covers \GoogleLogin\GoogleLoginHooks::onSetup()
	 */
	public function testOnSetupAuthoritativeNoOtherProviders() {
		$this->overrideConfigValues( [
			'GLAuthoritativeMode' => true,
			 MainConfigNames::AuthManagerConfig => [
				'primaryauth' => [
					GooglePrimaryAuthenticationProvider::class => [
						'class' => GooglePrimaryAuthenticationProvider::class,
					],
				],
			],
			MainConfigNames::InvalidUsernameCharacters => ':',
		] );

		$this->expectNotToPerformAssertions();
		try {
			GoogleLoginHooks::onSetup( [], SettingsBuilder::getInstance() );
		} catch ( ConfigurationError $exception ) {
			$this->fail( 'Exception should not be thrown' );
		}
	}

	/**
	 * @covers \GoogleLogin\GoogleLoginHooks::onSetup()
	 */
	public function testOnSetupNonAuthoritativeOtherProviders() {
		$this->overrideConfigValues( [
			'GLAuthoritativeMode' => false,
			 MainConfigNames::AuthManagerConfig => [
				'primaryauth' => [
					'AnyOtherProvider' => [],
					GooglePrimaryAuthenticationProvider::class => [
						'class' => GooglePrimaryAuthenticationProvider::class,
					],
				],
			],
		] );

		$this->expectNotToPerformAssertions();
		try {
			GoogleLoginHooks::onSetup( [], SettingsBuilder::getInstance() );
		} catch ( ConfigurationError $exception ) {
			$this->fail( 'Exception should not be thrown' );
		}
	}

	/**
	 * @covers \GoogleLogin\GoogleLoginHooks::onSetup()
	 */
	public function testOnSetupNonAuthoritativeAtDisallowedUserChar() {
		$this->overrideConfigValues( [
			'GLAuthoritativeMode' => false,
			 MainConfigNames::AuthManagerConfig => [
				'primaryauth' => [
					'AnyOtherProvider' => [],
					GooglePrimaryAuthenticationProvider::class => [
						'class' => GooglePrimaryAuthenticationProvider::class,
					],
				],
			],
			MainConfigNames::InvalidUsernameCharacters => '@',
		] );

		$this->expectNotToPerformAssertions();
		try {
			GoogleLoginHooks::onSetup( [], SettingsBuilder::getInstance() );
		} catch ( ConfigurationError $exception ) {
			$this->fail( 'Exception should not be thrown' );
		}
	}

	/**
	 * @covers \GoogleLogin\GoogleLoginHooks::onSetup()
	 */
	public function testOnSetupAuthoritativeAtDisallowedUserChar() {
		$this->overrideConfigValues( [
			'GLAuthoritativeMode' => true,
			 MainConfigNames::AuthManagerConfig => [
				'primaryauth' => [
					GooglePrimaryAuthenticationProvider::class => [
						'class' => GooglePrimaryAuthenticationProvider::class,
					],
				],
			],
			MainConfigNames::InvalidUsernameCharacters => '@',
		] );

		$this->expectException( ConfigurationError::class );

		GoogleLoginHooks::onSetup( [], SettingsBuilder::getInstance() );
	}

	/**
	 * @covers \GoogleLogin\GoogleLoginHooks::onSetup()
	 */
	public function testOnSetupAuthoritativeNoAtDisallowedUserChar() {
		$this->overrideConfigValues( [
			'GLAuthoritativeMode' => true,
			 MainConfigNames::AuthManagerConfig => [
				'primaryauth' => [
					GooglePrimaryAuthenticationProvider::class => [
						'class' => GooglePrimaryAuthenticationProvider::class,
					],
				],
			],
			MainConfigNames::InvalidUsernameCharacters => ':',
		] );

		$this->expectNotToPerformAssertions();
		try {
			GoogleLoginHooks::onSetup( [], SettingsBuilder::getInstance() );
		} catch ( ConfigurationError $exception ) {
			$this->fail( 'Exception should not be thrown' );
		}
	}

	public static function provideAllowedDomainsConfig() {
		return [
			[ true ],
			[ false ],
		];
	}
}
