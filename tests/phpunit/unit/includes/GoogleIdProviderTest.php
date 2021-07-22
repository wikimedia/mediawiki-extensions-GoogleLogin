<?php

namespace GoogleLogin;

use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\LoadBalancer;

class GoogleIdProviderTest extends MediaWikiUnitTestCase {

	/**
	 * @var IDatabase|MockObject
	 */
	private $dbConnection;

	/**
	 * @var LoadBalancer
	 */
	private $loadBalancer;

	protected function setUp(): void {
		parent::setUp();

		$this->dbConnection = $this->getMockBuilder( IDatabase::class )
				->disableOriginalConstructor()
				->getMock();

		$this->loadBalancer = $this->getMockBuilder( ILoadBalancer::class )
			->disableOriginalConstructor()
			->getMock();
		$this->loadBalancer->method( 'getConnection' )->willReturn( $this->dbConnection );
	}

	/**
	 * @covers \GoogleLogin\GoogleIdProvider::getFromUser()
	 */
	public function testGetFromUserAnonymousUser() {
		$this->dbConnection->expects( $this->never() )->method( 'select' );
		$googleIdProvider = new GoogleIdProvider( $this->loadBalancer );

		$user = new UserIdentityValue( 0, '127.0.0.1' );
		$this->assertEmpty( $googleIdProvider->getFromUser( $user ) );
	}

	/**
	 * @covers \GoogleLogin\GoogleIdProvider::getFromUser()
	 */
	public function testGetFromUserNoAssociatedIds() {
		$this->dbConnection->expects( $this->once() )
			->method( 'select' )
			->with( $this->anything(), $this->anything(), [ 'user_id' => 123 ] )
			->willReturn( false );
		$googleIdProvider = new GoogleIdProvider( $this->loadBalancer );

		$user = new UserIdentityValue( 123, __CLASS__ );
		$this->assertEmpty( $googleIdProvider->getFromUser( $user ) );
	}

	/**
	 * @covers \GoogleLogin\GoogleIdProvider::getFromUser()
	 */
	public function testGetFromUserAssociatedIds() {
		$aResult = (object)[ 'user_googleid' => 1 ];
		$anotherResult = (object)[ 'user_googleid' => 2 ];
		$this->dbConnection->expects( $this->once() )
			->method( 'select' )
			->with( $this->anything(), $this->anything(), [ 'user_id' => 123 ] )
			->willReturn( [ $aResult, $anotherResult ] );
		$googleIdProvider = new GoogleIdProvider( $this->loadBalancer );

		$user = new UserIdentityValue( 123, __CLASS__ );
		$this->assertEquals( [ 1, 2 ], $googleIdProvider->getFromUser( $user ) );
	}

	/**
	 * @dataProvider provideIsAssociated
	 * @covers \GoogleLogin\GoogleIdProvider::isAssociated()
	 */
	public function testIsAssociated( $resultCount, $expected ) {
		$this->dbConnection->expects( $this->once() )
			->method( 'selectRowCount' )
			->with( $this->anything(), $this->anything(), [ 'user_googleid' => 123 ] )
			->willReturn( $resultCount );
		$googleIdProvider = new GoogleIdProvider( $this->loadBalancer );

		$this->assertEquals( $expected, $googleIdProvider->isAssociated( 123 ) );
	}

	public function provideIsAssociated() {
		return [
			[ 0, false ],
			[ 1, true ],
			[ 42, true ],
		];
	}
}
