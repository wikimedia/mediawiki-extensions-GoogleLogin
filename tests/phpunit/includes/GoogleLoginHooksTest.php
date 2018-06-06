<?php

namespace GoogleLogin;

class GoogleLoginHooksTest extends \MediaWikiTestCase {

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
