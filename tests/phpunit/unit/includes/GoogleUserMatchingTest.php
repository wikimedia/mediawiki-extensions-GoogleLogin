<?php

namespace GoogleLogin;

use MediaWikiUnitTestCase;
use PHPUnit_Framework_MockObject_MockObject;
use stdClass;
use User;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

class GoogleUserMatchingTest extends MediaWikiUnitTestCase {
	/**
	 * @var PHPUnit_Framework_MockObject_MockObject
	 */
	private $dbConnection;
	private $loadBalancer;
	/**
	 * @var User
	 */
	private $loggedInUser;

	private $validToken = [
		'sub' => '123',
		'email' => 'test@example.com',
		'email_verified' => true,
	];

	protected function setUp() : void {
		parent::setUp();

		$this->dbConnection = $this->getMockBuilder( IDatabase::class )
			->disableOriginalConstructor()
			->getMock();

		$this->loadBalancer = $this->getMockBuilder( ILoadBalancer::class )
			->disableOriginalConstructor()
			->getMock();
		$this->loadBalancer->method( 'getConnection' )->willReturn( $this->dbConnection );

		$this->loggedInUser = User::newFromId( 100 );
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
		$aResult = new StdClass();
		$aResult->user_id = 1;
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
			->willReturn( new FakeResultWrapper( [ $aResult ] ) );
		$matchingService = new GoogleUserMatching( $this->loadBalancer );

		$user = $matchingService->getUserFromToken( $this->validToken );
		$this->assertSame( 1, $user->getId() );
	}

	/**
	 * @covers \GoogleLogin\GoogleUserMatching::getUserFromToken()
	 */
	public function testGetUserFromTokenMultipleEmailsLinked() {
		$aResult = new StdClass();
		$aResult->user_id = 1;
		$anotherResult = new StdClass();
		$anotherResult->user_id = 1;
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
		$userConnection = new StdClass();
		$userConnection->user_id = 100;

		$this->dbConnection->expects( $this->once() )
			->method( 'selectRow' )
			->with( $this->anything(), $this->anything(), [ 'user_googleid' => '123' ] )
			->willReturn( $userConnection );
		$matchingService = new GoogleUserMatching( $this->loadBalancer );

		$user = $matchingService->getUserFromToken( $this->validToken );

		$this->assertEquals( 100, $user->getId() );
	}

	/**
	 * @covers \GoogleLogin\GoogleUserMatching::match()
	 */
	public function testMatchAnonymousUser() {
		$matchingService = new GoogleUserMatching( $this->loadBalancer );

		$this->assertFalse( $matchingService->match( new User(), [] ) );
	}

	/**
	 * @covers \GoogleLogin\GoogleUserMatching::match()
	 */
	public function testMatchWithoutTokenId() {
		$matchingService = new GoogleUserMatching( $this->loadBalancer );

		$this->assertFalse( $matchingService->match( $this->loggedInUser, [] ) );
	}

	/**
	 * @covers \GoogleLogin\GoogleUserMatching::match()
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

		$this->assertTrue( $matchingService->match( $this->loggedInUser, $this->validToken ) );
	}

	/**
	 * @covers \GoogleLogin\GoogleUserMatching::unmatch()
	 */
	public function testUnmatchAnonymousUser() {
		$matchingService = new GoogleUserMatching( $this->loadBalancer );

		$this->assertFalse( $matchingService->unmatch( new User(), [] ) );
	}

	/**
	 * @covers \GoogleLogin\GoogleUserMatching::unmatch()
	 */
	public function testUnmatchWithoutTokenId() {
		$matchingService = new GoogleUserMatching( $this->loadBalancer );

		$this->assertFalse( $matchingService->unmatch( $this->loggedInUser, [] ) );
	}

	/**
	 * @covers \GoogleLogin\GoogleUserMatching::unmatch()
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

		$this->assertTrue( $matchingService->unmatch( $this->loggedInUser, $this->validToken ) );
	}
}
