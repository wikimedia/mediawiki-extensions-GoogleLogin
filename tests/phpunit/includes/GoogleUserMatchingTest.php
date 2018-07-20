<?php

use GoogleLogin\GoogleUserMatching;
use MediaWiki\MediaWikiServices;

class GoogleUserMatchingTest extends MediaWikiTestCase {
	/**
	 * @var PHPUnit_Framework_MockObject_MockObject
	 */
	private $dbConnection;
	private $loadBalancer;
	/**
	 * @var User
	 */
	private $loggedInUser;

	protected function setUp() {
		parent::setUp();

		$this->dbConnection = $this->getMockBuilder( Database::class )
			->disableOriginalConstructor()
			->getMock();

		$this->loadBalancer = $this->getMockBuilder( LoadBalancer::class )
			->disableOriginalConstructor()
			->getMock();
		$this->loadBalancer->method( 'getConnection' )->willReturn( $this->dbConnection );

		$this->loggedInUser = User::newFromId( 100 );
	}

	public function testGetUserFromTokenEmptyArray() {
		$matchingService =
			new GoogleUserMatching( MediaWikiServices::getInstance()->getDBLoadBalancer() );

		$this->assertNull( $matchingService->getUserFromToken( [] ) );
	}

	public function testGetUserFromTokenTokenNotAssociated() {
		$this->dbConnection->expects( $this->once() )
			->method( 'selectRow' )
			->willReturn( false );
		$matchingService = new GoogleUserMatching( $this->loadBalancer );

		$this->assertNull( $matchingService->getUserFromToken( [ 'sub' => '123' ] ) );
	}

	public function testGetUserFromTokenTokenAssociated() {
		$userConnection = new StdClass();
		$userConnection->user_id = 100;

		$this->dbConnection->expects( $this->once() )
			->method( 'selectRow' )
			->with( $this->anything(), $this->anything(), [ 'user_googleid' => '123' ] )
			->willReturn( $userConnection );
		$matchingService = new GoogleUserMatching( $this->loadBalancer );

		$user = $matchingService->getUserFromToken( [ 'sub' => '123' ] );

		$this->assertEquals( 100, $user->getId() );
	}

	public function testMatchAnonymousUser() {
		$matchingService = new GoogleUserMatching( $this->loadBalancer );

		$this->assertFalse( $matchingService->match( new User(), [] ) );
	}

	public function testMatchWithoutTokenId() {
		$matchingService = new GoogleUserMatching( $this->loadBalancer );

		$this->assertFalse( $matchingService->match( $this->loggedInUser, [] ) );
	}

	public function testMatchWithTokenIdAndUser() {
		$this->dbConnection->expects( $this->once() )
			->method( 'insert' )
			->with( 'user_google_user', [
				'user_id' => 100,
				'user_googleid' => '123'
			] )
			->willReturn( true );
		$matchingService = new GoogleUserMatching( $this->loadBalancer );

		$this->assertTrue( $matchingService->match( $this->loggedInUser, [ 'sub' => '123' ] ) );
	}

	public function testUnmatchAnonymousUser() {
		$matchingService = new GoogleUserMatching( $this->loadBalancer );

		$this->assertFalse( $matchingService->unmatch( new User(), [] ) );
	}

	public function testUnmatchWithoutTokenId() {
		$matchingService = new GoogleUserMatching( $this->loadBalancer );

		$this->assertFalse( $matchingService->unmatch( $this->loggedInUser, [] ) );
	}

	public function testUnmatchWithTokenIdAndUser() {
		$this->dbConnection->expects( $this->once() )
			->method( 'delete' )
			->with( 'user_google_user', [
				'user_id' => 100,
				'user_googleid' => '123'
			] )
			->willReturn( true );
		$matchingService = new GoogleUserMatching( $this->loadBalancer );

		$this->assertTrue( $matchingService->unmatch( $this->loggedInUser, [ 'sub' => '123' ] ) );
	}
}
