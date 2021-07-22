<?php

namespace GoogleLogin;

use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\LoadBalancer;

class GoogleUserMatchingTest extends MediaWikiIntegrationTestCase {

	/**
	 * @var IDatabase|MockObject
	 */
	private $dbConnection;

	/**
	 * @var LoadBalancer
	 */
	private $loadBalancer;

	/**
	 * @var UserIdentity
	 */
	private $loggedInUser;

	private $validToken = [
		'sub' => '123',
		'email' => 'test@example.com',
		'email_verified' => true,
	];

	protected function setUp(): void {
		parent::setUp();

		$this->dbConnection = $this->getMockBuilder( IDatabase::class )
			->disableOriginalConstructor()
			->getMock();

		$this->loadBalancer = $this->getMockBuilder( ILoadBalancer::class )
			->disableOriginalConstructor()
			->getMock();
		$this->loadBalancer->method( 'getConnection' )->willReturn( $this->dbConnection );

		$this->loggedInUser = new UserIdentityValue( 100, __CLASS__ );
	}

	/**
	 * @covers \GoogleLogin\GoogleUserMatching::getUserFromToken()
	 */
	public function testGetUserFromTokenEmptyArray() {
		$matchingService =
			new GoogleUserMatching( $this->loadBalancer );

		$this->assertNull( $matchingService->getUserFromToken( [] ) );
	}

	/**
	 * @covers \GoogleLogin\GoogleUserMatching::getUserFromToken()
	 */
	public function testGetUserFromTokenTokenNotAssociated() {
		$this->dbConnection->expects( $this->once() )
			->method( 'selectRow' )
			->willReturn( false );
		$matchingService = new GoogleUserMatching( $this->loadBalancer );

		$this->assertNull( $matchingService->getUserFromToken( $this->validToken ) );
	}

	/**
	 * @covers \GoogleLogin\GoogleUserMatching::getUserFromToken()
	 */
	public function testGetUserFromTokenNoEmailAttribute() {
		$this->dbConnection->expects( $this->once() )
			->method( 'selectRow' )
			->willReturn( false );
		$this->dbConnection->expects( $this->never() )->method( 'select' );
		$matchingService = new GoogleUserMatching( $this->loadBalancer );

		$this->assertNull( $matchingService->getUserFromToken( [ 'sub' => '123' ] ) );
	}

	/**
	 * @covers \GoogleLogin\GoogleUserMatching::getUserFromToken()
	 */
	public function testGetUserFromTokenEmailNotVerified() {
		$this->dbConnection->expects( $this->once() )
			->method( 'selectRow' )
			->willReturn( false );
		$this->dbConnection->expects( $this->never() )->method( 'select' );
		$matchingService = new GoogleUserMatching( $this->loadBalancer );

		$token = $this->validToken;
		$token['email_verified'] = false;
		$this->assertNull( $matchingService->getUserFromToken( $token ) );
	}

	/**
	 * @covers \GoogleLogin\GoogleUserMatching::getUserFromToken()
	 */
	public function testGetUserFromTokenOneEmailLinked() {
		$this->dbConnection->expects( $this->once() )
			->method( 'selectRow' )
			->willReturn( false );
		$this->dbConnection->expects( $this->once() )
			->method( 'select' )
			->with(
				'user',
				[ 'user_id' ],
				[ 'user_email' => 'test@example.com', 'user_email_authenticated IS NOT NULL' ]
			)
			->willReturn( new FakeResultWrapper( [ (object)[ 'user_id' => 1 ] ] ) );
		$matchingService = new GoogleUserMatching( $this->loadBalancer );

		$user = $matchingService->getUserFromToken( $this->validToken );
		$this->assertSame( 1, $user->getId() );
	}

	/**
	 * @covers \GoogleLogin\GoogleUserMatching::getUserFromToken()
	 */
	public function testGetUserFromTokenMultipleEmailsLinked() {
		$aResult = (object)[ 'user_id' => 1 ];
		$anotherResult = (object)[ 'user_id' => 1 ];
		$this->dbConnection->expects( $this->once() )
			->method( 'selectRow' )
			->willReturn( false );
		$this->dbConnection->expects( $this->once() )
			->method( 'select' )
			->with(
				'user',
				[ 'user_id' ],
				[ 'user_email' => 'test@example.com', 'user_email_authenticated IS NOT NULL' ]
			)
			->willReturn( new FakeResultWrapper( [ $aResult, $anotherResult ] ) );
		$matchingService = new GoogleUserMatching( $this->loadBalancer );

		$this->assertNull( $matchingService->getUserFromToken( $this->validToken ) );
	}

	/**
	 * @covers \GoogleLogin\GoogleUserMatching::getUserFromToken()
	 */
	public function testGetUserFromTokenTokenAssociated() {
		$this->dbConnection->expects( $this->once() )
			->method( 'selectRow' )
			->with( $this->anything(), $this->anything(), [ 'user_googleid' => '123' ] )
			->willReturn( (object)[ 'user_id' => 100 ] );
		$matchingService = new GoogleUserMatching( $this->loadBalancer );

		$user = $matchingService->getUserFromToken( $this->validToken );

		$this->assertEquals( 100, $user->getId() );
	}

	/**
	 * @covers \GoogleLogin\GoogleUserMatching::matchUser()
	 */
	public function testMatchAnonymousUser() {
		$matchingService = new GoogleUserMatching( $this->loadBalancer );

		$user = new UserIdentityValue( 0, '127.0.0.1' );
		$this->assertFalse( $matchingService->matchUser( $user, [] ) );
	}

	/**
	 * @covers \GoogleLogin\GoogleUserMatching::matchUser()
	 */
	public function testMatchWithoutTokenId() {
		$matchingService = new GoogleUserMatching( $this->loadBalancer );

		$this->assertFalse( $matchingService->matchUser( $this->loggedInUser, [] ) );
	}

	/**
	 * @covers \GoogleLogin\GoogleUserMatching::matchUser()
	 */
	public function testMatchWithTokenIdAndUser() {
		$this->dbConnection->expects( $this->once() )
			->method( 'insert' )
			->with( 'user_google_user', [
				'user_id' => 100,
				'user_googleid' => '123'
			] )
			->willReturn( true );
		$matchingService = new GoogleUserMatching( $this->loadBalancer );

		$this->assertTrue( $matchingService->matchUser( $this->loggedInUser, $this->validToken ) );
	}

	/**
	 * @covers \GoogleLogin\GoogleUserMatching::unmatchUser()
	 */
	public function testUnmatchAnonymousUser() {
		$matchingService = new GoogleUserMatching( $this->loadBalancer );

		$user = new UserIdentityValue( 0, '127.0.0.1' );
		$this->assertFalse( $matchingService->unmatchUser( $user, [] ) );
	}

	/**
	 * @covers \GoogleLogin\GoogleUserMatching::unmatchUser()
	 */
	public function testUnmatchWithoutTokenId() {
		$matchingService = new GoogleUserMatching( $this->loadBalancer );

		$this->assertFalse( $matchingService->unmatchUser( $this->loggedInUser, [] ) );
	}

	/**
	 * @covers \GoogleLogin\GoogleUserMatching::unmatchUser()
	 */
	public function testUnmatchWithTokenIdAndUser() {
		$this->dbConnection->expects( $this->once() )
			->method( 'delete' )
			->with( 'user_google_user', [
				'user_id' => 100,
				'user_googleid' => '123'
			] )
			->willReturn( true );
		$matchingService = new GoogleUserMatching( $this->loadBalancer );

		$this->assertTrue( $matchingService->unmatchUser( $this->loggedInUser, $this->validToken ) );
	}
}
